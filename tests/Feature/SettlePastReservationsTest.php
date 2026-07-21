<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Service;
use App\Support\Reservations\ReactivateReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlePastReservationsTest extends TestCase
{
    use RefreshDatabase;

    private function reservation(int $price, string $date, ReservationStatus $status = ReservationStatus::Confirmed): Reservation
    {
        return Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => $price])->getKey(),
            'status' => $status,
            'payment_status' => PaymentStatus::Unpaid,
            'reservation_date' => $date,
            'settled_at' => null,
        ]);
    }

    private function pay(Reservation $reservation, int $amount): Payment
    {
        return $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => $amount,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function test_observer_settles_past_confirmed_when_paid(): void
    {
        $reservation = $this->reservation(800, today()->subDay()->toDateString());

        $this->pay($reservation, 800);

        $this->assertNotNull($reservation->fresh()->settled_at);
    }

    public function test_future_paid_visit_is_not_settled(): void
    {
        $reservation = $this->reservation(800, today()->addDays(3)->toDateString());

        $this->pay($reservation, 800);

        $this->assertNull($reservation->fresh()->settled_at);
    }

    public function test_past_unpaid_visit_is_not_settled_by_cron(): void
    {
        $reservation = $this->reservation(800, today()->subDay()->toDateString());

        $this->artisan('reservations:settle-past')->assertSuccessful();

        $this->assertNull($reservation->fresh()->settled_at);
    }

    public function test_cron_settles_free_past_visit(): void
    {
        // Price 0 → nothing owed, but no payment event ever fires, so the cron is
        // the only thing that can settle it.
        $reservation = $this->reservation(0, today()->subDay()->toDateString());

        $this->artisan('reservations:settle-past')->assertSuccessful();

        $this->assertNotNull($reservation->fresh()->settled_at);
    }

    public function test_cron_leaves_plain_cancellation_unsettled(): void
    {
        // A free self-cancel (no payments) is closed but not "Vybaveno".
        $reservation = $this->reservation(800, today()->subDay()->toDateString(), ReservationStatus::Cancelled);

        $this->artisan('reservations:settle-past')->assertSuccessful();

        $this->assertNull($reservation->fresh()->settled_at);
    }

    public function test_settled_marker_is_monotonic(): void
    {
        $reservation = $this->reservation(800, today()->subDay()->toDateString());
        $payment = $this->pay($reservation, 800);

        $this->assertNotNull($reservation->fresh()->settled_at);

        // Reverting the payment must not un-settle an already-handled reservation.
        $payment->delete();

        $this->assertNotNull($reservation->fresh()->settled_at);
        $this->assertSame(PaymentStatus::Unpaid, $reservation->fresh()->payment_status);
    }

    public function test_reactivation_clears_settled_marker(): void
    {
        $reservation = $this->reservation(800, today()->subDay()->toDateString(), ReservationStatus::Cancelled);
        $reservation->update(['settled_at' => now(), 'doctor_note_requested_at' => now(), 'doctor_note_resolved_at' => now()]);

        app(ReactivateReservation::class)->handle($reservation, notifyClient: false);

        $reservation->refresh();
        $this->assertNull($reservation->settled_at);
        $this->assertNull($reservation->doctor_note_requested_at);
        $this->assertNull($reservation->doctor_note_resolved_at);
    }
}
