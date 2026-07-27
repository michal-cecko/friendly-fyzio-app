<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\LessonBooking;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\Clients\DeactivateAccount;
use App\Support\Reservations\ClientReservationActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Deactivating an account is a release, not just a login lock: a blacklisted client
 * must stop holding calendar slots, course capacity and waitlist spots. Money owed
 * for services already rendered is *not* written off — only requests for the things
 * being cancelled are withdrawn.
 */
class DeactivateAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->client = User::factory()->customer()->create(['email_verified_at' => now()]);
        $this->service = Service::factory()->create(['price' => 1000]);
    }

    private function reservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'reservation_date' => today()->addDays(7)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            ...$attributes,
        ]);
    }

    public function test_upcoming_reservations_are_cancelled_and_the_slot_freed(): void
    {
        $upcoming = $this->reservation();
        $alsoUpcoming = $this->reservation(['reservation_date' => today()->addDays(20)->toDateString()]);

        $released = app(DeactivateAccount::class)($this->client);

        $this->assertNotNull($this->client->fresh()->deactivated_at);
        $this->assertSame(2, $released['reservations']);

        foreach ([$upcoming, $alsoUpcoming] as $reservation) {
            $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
            $this->assertSame('Deaktivace účtu', $reservation->fresh()->cancellation_reason);
        }
    }

    public function test_past_reservations_are_left_alone(): void
    {
        $past = $this->reservation(['reservation_date' => today()->subWeek()->toDateString()]);

        app(DeactivateAccount::class)($this->client);

        // Rewriting history would falsify the record of a visit that happened.
        $this->assertSame(ReservationStatus::Confirmed, $past->fresh()->status);
    }

    public function test_a_debt_for_a_visit_already_rendered_survives(): void
    {
        $past = $this->reservation(['reservation_date' => today()->subWeek()->toDateString()]);
        $debt = $past->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 1000,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        app(DeactivateAccount::class)($this->client);

        $this->assertSame(PaymentStatus::Unpaid, $debt->fresh()->status);
    }

    public function test_a_prepayment_for_a_cancelled_upcoming_visit_is_withdrawn_not_deleted(): void
    {
        $upcoming = $this->reservation();
        $prepayment = $upcoming->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 1000,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        app(DeactivateAccount::class)($this->client);

        $this->assertModelExists($prepayment);
        $this->assertSame(PaymentStatus::Cancelled, $prepayment->fresh()->status);
    }

    public function test_a_paid_payment_is_never_touched(): void
    {
        $upcoming = $this->reservation();
        $paid = $upcoming->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 1000,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        app(DeactivateAccount::class)($this->client);

        $this->assertSame(PaymentStatus::Paid, $paid->fresh()->status);
    }

    public function test_the_person_is_removed_from_all_courses_and_lessons(): void
    {
        $enrollment = CourseEnrollment::factory()->create([
            'client_id' => $this->client->id,
            'status' => CourseEnrollmentStatus::Active,
        ]);

        $booking = LessonBooking::factory()->create([
            'client_id' => $this->client->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $released = app(DeactivateAccount::class)($this->client);

        $this->assertSame(1, $released['enrollments']);
        $this->assertSame(1, $released['bookings']);
        $this->assertSame(CourseEnrollmentStatus::Cancelled, $enrollment->fresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_waitlist_spots_are_given_up(): void
    {
        $series = CourseSeries::factory()->create();

        WaitlistEntry::factory()->create([
            'client_id' => $this->client->id,
            'waitlistable_type' => $series->getMorphClass(),
            'waitlistable_id' => $series->getKey(),
        ]);

        $released = app(DeactivateAccount::class)($this->client);

        $this->assertSame(1, $released['waitlist']);
        $this->assertSame(0, $this->client->waitlistEntries()->count());
    }

    public function test_other_pending_doctor_notes_are_closed_out(): void
    {
        $pending = $this->reservation([
            'reservation_date' => today()->subWeek()->toDateString(),
            'status' => ReservationStatus::Cancelled,
            'doctor_note_requested_at' => now()->subDay(),
        ]);

        app(DeactivateAccount::class)($this->client);

        // The note is never coming — it must not sit in the staff work list forever.
        $this->assertNotNull($pending->fresh()->doctor_note_resolved_at);
    }

    public function test_the_preview_names_what_will_be_released(): void
    {
        $this->reservation();
        $this->reservation(['reservation_date' => today()->addDays(9)->toDateString()]);

        CourseEnrollment::factory()->create([
            'client_id' => $this->client->id,
            'status' => CourseEnrollmentStatus::Active,
        ]);

        $sentence = app(DeactivateAccount::class)->previewSentence($this->client);

        $this->assertStringContainsString('2 rezervace', $sentence);
        $this->assertStringContainsString('1 přihlášku na kurz', $sentence);
    }

    public function test_the_preview_is_null_when_there_is_nothing_to_release(): void
    {
        $this->assertNull(app(DeactivateAccount::class)->previewSentence($this->client));
    }

    public function test_a_reservation_can_be_excluded_when_the_caller_already_cancelled_it(): void
    {
        $trigger = $this->reservation();
        $other = $this->reservation(['reservation_date' => today()->addDays(9)->toDateString()]);

        $released = app(DeactivateAccount::class)($this->client, except: $trigger);

        $this->assertSame(1, $released['reservations']);
        $this->assertSame(ReservationStatus::Cancelled, $other->fresh()->status);
    }

    public function test_another_clients_records_are_untouched(): void
    {
        $stranger = User::factory()->customer()->create(['email_verified_at' => now()]);
        $strangers = Reservation::factory()->create([
            'client_id' => $stranger->id,
            'service_id' => $this->service->id,
            'reservation_date' => today()->addDays(7)->toDateString(),
            'status' => ReservationStatus::Confirmed,
        ]);

        app(DeactivateAccount::class)($this->client);

        $this->assertSame(ReservationStatus::Confirmed, $strangers->fresh()->status);
        $this->assertNull($stranger->fresh()->deactivated_at);
    }

    /**
     * The client's own "I won't pay" refusal must cascade exactly like an admin
     * pressing "Deaktivovat účet".
     */
    public function test_the_storno_refusal_releases_everything_too(): void
    {
        $storno = $this->reservation();
        $other = $this->reservation(['reservation_date' => today()->addDays(9)->toDateString()]);

        app(ClientReservationActions::class)->cancelAndDeactivate($storno);

        $this->assertNotNull($this->client->fresh()->deactivated_at);
        $this->assertSame(ReservationStatus::Cancelled, $storno->fresh()->status);
        $this->assertSame(ReservationStatus::Cancelled, $other->fresh()->status);
        $this->assertSame('Pozdní storno – bez úhrady', $storno->fresh()->cancellation_reason);
        $this->assertSame('Deaktivace účtu', $other->fresh()->cancellation_reason);
    }

    public function test_a_cancelled_payment_is_no_longer_an_open_debt(): void
    {
        $upcoming = $this->reservation();
        $upcoming->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 1000,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        app(DeactivateAccount::class)($this->client);

        $this->assertFalse($upcoming->fresh()->hasUnpaidStornoFee());
        $this->assertFalse(
            Payment::query()->whereIn('status', PaymentStatus::openValues())->where('client_id', $this->client->id)->exists(),
        );
    }
}
