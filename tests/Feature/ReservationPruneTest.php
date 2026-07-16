<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_delete_keeps_payments_but_unlinks_them_with_history(): void
    {
        $reservation = $this->reservation();
        $payment = $this->payment($reservation);

        $this->assertNotEmpty($payment->payable_label);

        $reservation->forceDelete();

        $payment->refresh();

        $this->assertNull(Reservation::withTrashed()->find($reservation->getKey()));
        $this->assertNull($payment->payable_id);
        // Type + label survive so the accounting record still says what it was for.
        $this->assertSame('reservation', $payment->payable_type);
        $this->assertNotEmpty($payment->payable_label);
    }

    public function test_prune_purges_reservations_trashed_over_30_days(): void
    {
        $old = $this->reservation();
        $old->delete();
        $old->forceFill(['deleted_at' => now()->subDays(31)])->save();

        $recent = $this->reservation();
        $recent->delete();

        $active = $this->reservation();

        $this->artisan('model:prune', ['--model' => [Reservation::class]])->assertSuccessful();

        $this->assertNull(Reservation::withTrashed()->find($old->getKey()));
        $this->assertNotNull(Reservation::withTrashed()->find($recent->getKey()));
        $this->assertNotNull(Reservation::find($active->getKey()));
    }

    public function test_prune_keeps_payments_of_purged_reservation(): void
    {
        $reservation = $this->reservation();
        $payment = $this->payment($reservation);

        $reservation->delete();
        $reservation->forceFill(['deleted_at' => now()->subDays(31)])->save();

        $this->artisan('model:prune', ['--model' => [Reservation::class]])->assertSuccessful();

        $payment->refresh();

        $this->assertNull(Reservation::withTrashed()->find($reservation->getKey()));
        $this->assertNull($payment->payable_id);
        $this->assertSame('reservation', $payment->payable_type);
    }

    private function reservation(): Reservation
    {
        return Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => 800])->getKey(),
            'status' => ReservationStatus::Cancelled,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
    }

    private function payment(Reservation $reservation): Payment
    {
        return $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 800,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
