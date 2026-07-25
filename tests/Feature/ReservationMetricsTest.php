<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Service;
use App\Support\Reservations\ReservationMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_counts_group_by_status(): void
    {
        Reservation::factory()->count(3)->create(['status' => ReservationStatus::Pending]);
        Reservation::factory()->count(2)->create(['status' => ReservationStatus::Confirmed]);
        Reservation::factory()->create(['status' => ReservationStatus::Cancelled]);

        $counts = ReservationMetrics::statusCounts(Reservation::query());

        $this->assertSame(3, $counts[ReservationStatus::Pending->value]);
        $this->assertSame(2, $counts[ReservationStatus::Confirmed->value]);
        $this->assertSame(1, $counts[ReservationStatus::Cancelled->value]);
    }

    public function test_status_counts_respect_a_prefiltered_query(): void
    {
        $service = Service::factory()->create();
        $other = Service::factory()->create();

        Reservation::factory()->count(2)->create([
            'service_id' => $service->getKey(),
            'status' => ReservationStatus::Confirmed,
        ]);
        Reservation::factory()->create([
            'service_id' => $other->getKey(),
            'status' => ReservationStatus::Confirmed,
        ]);

        $counts = ReservationMetrics::statusCounts(
            Reservation::query()->where('service_id', $service->getKey()),
        );

        // Only the two rows matching the pre-applied filter are counted.
        $this->assertSame(2, $counts[ReservationStatus::Confirmed->value]);
    }

    public function test_revenue_sums_only_paid_payments_of_filtered_reservations(): void
    {
        $reservation = Reservation::factory()->create();
        $morph = (new Reservation)->getMorphClass();

        Payment::factory()->paid()->create([
            'payable_type' => $morph,
            'payable_id' => $reservation->getKey(),
            'amount' => 500,
        ]);
        // Unpaid payment on the same reservation must be excluded.
        Payment::factory()->create([
            'payable_type' => $morph,
            'payable_id' => $reservation->getKey(),
            'status' => PaymentStatus::Unpaid,
            'amount' => 300,
        ]);
        // Paid payment on a reservation outside the filtered set must be excluded.
        $otherReservation = Reservation::factory()->create();
        Payment::factory()->paid()->create([
            'payable_type' => $morph,
            'payable_id' => $otherReservation->getKey(),
            'amount' => 999,
        ]);

        $revenue = ReservationMetrics::revenue(
            Reservation::query()->whereKey($reservation->getKey()),
        );

        $this->assertSame(500, $revenue);
    }

    public function test_outstanding_counts_and_sums_unpaid_and_overdue(): void
    {
        $service = Service::factory()->create(['price' => 700]);

        Reservation::factory()->create(['service_id' => $service->getKey(), 'payment_status' => PaymentStatus::Unpaid]);
        Reservation::factory()->create(['service_id' => $service->getKey(), 'payment_status' => PaymentStatus::Overdue]);
        Reservation::factory()->create(['service_id' => $service->getKey(), 'payment_status' => PaymentStatus::Paid]);

        $outstanding = ReservationMetrics::outstanding(Reservation::query());

        $this->assertSame(2, $outstanding['count']);
        $this->assertSame(1400, $outstanding['amount']);
    }

    public function test_doctor_note_pending_counts_unresolved_promises(): void
    {
        Reservation::factory()->create([
            'doctor_note_requested_at' => now(),
            'doctor_note_resolved_at' => null,
        ]);
        Reservation::factory()->create([
            'doctor_note_requested_at' => now(),
            'doctor_note_resolved_at' => now(),
        ]);
        Reservation::factory()->create([
            'doctor_note_requested_at' => null,
        ]);

        $this->assertSame(1, ReservationMetrics::doctorNotePending(Reservation::query()));
    }

    public function test_unsettled_past_counts_past_confirmed_without_settlement(): void
    {
        // Past, confirmed, not settled → counted.
        Reservation::factory()->create([
            'reservation_date' => now()->subDay()->toDateString(),
            'status' => ReservationStatus::Confirmed,
            'settled_at' => null,
        ]);
        // Past but already settled → excluded.
        Reservation::factory()->create([
            'reservation_date' => now()->subDay()->toDateString(),
            'status' => ReservationStatus::Confirmed,
            'settled_at' => now(),
        ]);
        // Future confirmed → excluded.
        Reservation::factory()->create([
            'reservation_date' => now()->addDay()->toDateString(),
            'status' => ReservationStatus::Confirmed,
            'settled_at' => null,
        ]);

        $this->assertSame(1, ReservationMetrics::unsettledPast(Reservation::query()));
    }
}
