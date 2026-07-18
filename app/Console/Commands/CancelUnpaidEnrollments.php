<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Models\CourseEnrollment;
use App\Models\OneTimeLessonBooking;
use App\Models\WorkshopRegistration;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\EnrollmentEmailContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

    protected $description = 'Cancel course/lesson/workshop sign-ups whose payment hold expired and offer the spots to the waitlist';

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
                $this->cancel($enrollment, CourseEnrollmentStatus::Cancelled, EnrollmentEmailContext::offerTokens($enrollment->series));
                $cancelled++;
            });

        OneTimeLessonBooking::query()
            ->whereIn('status', BookingStatus::occupying())
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->whereHas('lesson', fn (Builder $query) => $query->whereDate('lesson_date', '>=', today()))
            ->tap(fn (Builder $query) => $this->withExpiredHold($query))
            ->with(['lesson.course', 'client'])
            ->each(function (OneTimeLessonBooking $booking) use (&$cancelled): void {
                $this->cancel($booking, BookingStatus::Cancelled, EnrollmentEmailContext::offerTokens($booking->lesson));
                $cancelled++;
            });

        WorkshopRegistration::query()
            ->whereIn('status', BookingStatus::occupying())
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->whereHas('workshop', fn (Builder $query) => $query->whereDate('workshop_date', '>=', today()))
            ->tap(fn (Builder $query) => $this->withExpiredHold($query))
            ->with(['workshop', 'client'])
            ->each(function (WorkshopRegistration $registration) use (&$cancelled): void {
                $this->cancel($registration, BookingStatus::Cancelled, EnrollmentEmailContext::offerTokens($registration->workshop));
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

    /**
     * @param  array<string, string>  $offerTokens
     */
    protected function cancel(CourseEnrollment|OneTimeLessonBooking|WorkshopRegistration $signup, CourseEnrollmentStatus|BookingStatus $cancelledStatus, array $offerTokens): void
    {
        DB::transaction(function () use ($signup, $cancelledStatus): void {
            // Withdraw the expired payment request so the overdue reminder sweep
            // never chases money for a cancelled spot.
            $signup->payments()
                ->whereIn('status', [PaymentStatus::Unpaid->value, PaymentStatus::Overdue->value])
                ->delete();

            // Fires the model observer, which promotes the waitlist.
            $signup->update(['status' => $cancelledStatus]);
        });

        $client = $signup->client;

        if ($client !== null && filled($client->email)) {
            $client->notify(new EnrollmentTemplateNotification(EmailTemplateKey::EnrollmentAutoCancelled, [
                'jmeno' => EnrollmentEmailContext::firstName($client),
                ...$offerTokens,
                'duvod' => 'Platba nebyla připsána v rezervační lhůtě',
            ]));
        }
    }
}
