<?php

namespace Tests\Feature\Enrollments;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Models\CourseSeries;
use App\Models\WaitlistEntry;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\CloseOverInvitedLosers;
use App\Support\Enrollments\OfferSpotToEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The "kdo dřív zaplatí" resolver: once an over-invited offer fills by paid
 * count, the slower unpaid holders are closed out. Exercised through the service
 * directly, because the observer defers it past commit (DB::afterCommit).
 */
class CloseOverInvitedLosersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function openSeries(int $capacity): CourseSeries
    {
        return CourseSeries::factory()->create([
            'capacity' => $capacity,
            'price' => 2000,
            'status' => CourseSeriesStatus::Open,
            'visibility' => CourseSeriesVisibility::Public,
            'start_date' => today()->addWeeks(2)->toDateString(),
            'end_date' => today()->addWeeks(14)->toDateString(),
        ]);
    }

    private function raceInvite(CourseSeries $series, int $count): void
    {
        $entries = WaitlistEntry::factory()->count($count)->forWaitlistable($series)->create(['client_id' => null]);

        app(OfferSpotToEntry::class)->inviteMany($series, $entries, enforceCapacity: false);
    }

    public function test_paying_to_capacity_closes_the_slower_unpaid_holders(): void
    {
        $series = $this->openSeries(capacity: 1);
        $this->raceInvite($series, 3);

        $this->assertSame(3, $series->enrollments()->where('status', CourseEnrollmentStatus::Active)->count());

        $winnerEnrollment = $series->enrollments()->first();
        $winnerPayment = $winnerEnrollment->payments()->sole();
        $winnerPayment->update(['status' => PaymentStatus::Paid]);

        app(CloseOverInvitedLosers::class)->afterPayment($winnerPayment->fresh());

        // Winner stays; the two slower holders are cancelled and their payment
        // requests withdrawn.
        $this->assertSame(CourseEnrollmentStatus::Active, $winnerEnrollment->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $winnerEnrollment->fresh()->payment_status);

        $this->assertSame(2, $series->enrollments()->where('status', CourseEnrollmentStatus::Cancelled)->count());
        $this->assertSame(0, $series->enrollments()
            ->where('status', CourseEnrollmentStatus::Cancelled)
            ->get()
            ->sum(fn ($e) => $e->payments()->whereIn('status', [PaymentStatus::Unpaid->value, PaymentStatus::Overdue->value])->count()));

        Notification::assertSentTimes(
            EnrollmentTemplateNotification::class,
            // 3 offer e-mails + 2 auto-cancel e-mails.
            5
        );
    }

    public function test_a_not_yet_full_offer_closes_nobody(): void
    {
        $series = $this->openSeries(capacity: 2);
        $this->raceInvite($series, 2);

        $enrollment = $series->enrollments()->first();
        $payment = $enrollment->payments()->sole();
        $payment->update(['status' => PaymentStatus::Paid]);

        app(CloseOverInvitedLosers::class)->afterPayment($payment->fresh());

        // Paid count (1) is below capacity (2), so the other holder keeps its spot.
        $this->assertSame(0, $series->enrollments()->where('status', CourseEnrollmentStatus::Cancelled)->count());
    }

    public function test_the_auto_cancel_email_explains_a_faster_payer_took_the_spot(): void
    {
        $series = $this->openSeries(capacity: 1);
        $this->raceInvite($series, 2);

        $winnerPayment = $series->enrollments()->first()->payments()->sole();
        $winnerPayment->update(['status' => PaymentStatus::Paid]);

        app(CloseOverInvitedLosers::class)->afterPayment($winnerPayment->fresh());

        Notification::assertSentTo(
            $series->enrollments()->where('status', CourseEnrollmentStatus::Cancelled)->sole()->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::EnrollmentAutoCancelled
                && str_contains((string) ($n->tokens['duvod'] ?? ''), 'obsazeno')
        );
    }
}
