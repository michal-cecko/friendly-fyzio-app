<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Models\CourseEnrollment;
use App\Models\OneOffEventBooking;
use App\Support\Enrollments\CancelSignup;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * The enrollment hold-window sweep (docs §4.1: "automatické storno pri
 * nezaplatení do X dní"): a sign-up whose QR payment request ran past its due
 * date without being paid is cancelled, its payment request withdrawn, the
 * client informed — and the freed spot goes to the waitlist (via the model
 * observers). Only sign-ups holding an expired payment request are touched,
 * so admin-created records without a requested payment are never swept.
 */
class CancelUnpaidEnrollments extends Command
{
    protected $signature = 'enrollments:cancel-unpaid';

    protected $description = 'Cancel course/event sign-ups whose payment hold expired and offer the spots to the waitlist';

    public function handle(): int
    {
        $cancelled = 0;

        CourseEnrollment::query()
            ->where('status', CourseEnrollmentStatus::Active)
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->whereHas('series', fn (Builder $query) => $query->whereDate('end_date', '>=', today()))
            ->tap(fn (Builder $query) => $this->withExpiredHold($query))
            ->with(['series.course', 'client'])
            ->each(function (CourseEnrollment $enrollment) use (&$cancelled): void {
                $this->cancel($enrollment);
                $cancelled++;
            });

        OneOffEventBooking::query()
            ->whereIn('status', BookingStatus::occupying())
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->whereHas('event', fn (Builder $query) => $query->whereDate('event_date', '>=', today()))
            ->tap(fn (Builder $query) => $this->withExpiredHold($query))
            ->with(['event', 'client'])
            ->each(function (OneOffEventBooking $booking) use (&$cancelled): void {
                $this->cancel($booking);
                $cancelled++;
            });

        $this->info("Zrušeno {$cancelled} nezaplacených přihlášek po lhůtě.");

        return self::SUCCESS;
    }

    /**
     * Sign-ups whose unpaid payment request is past its due date.
     */
    protected function withExpiredHold(Builder $query): Builder
    {
        return $query->whereHas('payments', fn (Builder $payments) => $payments
            ->whereIn('status', [PaymentStatus::Unpaid->value, PaymentStatus::Overdue->value])
            ->whereDate('due_at', '<', today()));
    }

    protected function cancel(CourseEnrollment|OneOffEventBooking $signup): void
    {
        app(CancelSignup::class)(
            $signup,
            emailKey: EmailTemplateKey::EnrollmentAutoCancelled,
            reason: 'Platba nebyla připsána v rezervační lhůtě',
        );
    }
}
