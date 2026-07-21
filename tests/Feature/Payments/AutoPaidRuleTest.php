<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Models\OneOffEventBooking;
use App\Models\Reservation;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoPaidRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_payment_covering_price_marks_reservation_paid(): void
    {
        $reservation = $this->reservation(800);

        $this->payFor($reservation, 800);

        $this->assertSame(PaymentStatus::Paid, $reservation->fresh()->payment_status);
    }

    public function test_paid_payment_marks_course_enrollment_paid_with_paid_at(): void
    {
        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => CourseSeries::factory()->create(['price' => 1200])->getKey(),
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        $this->payFor($enrollment, 1200);

        $enrollment->refresh();

        $this->assertSame(PaymentStatus::Paid, $enrollment->payment_status);
        $this->assertNotNull($enrollment->paid_at);
    }

    public function test_paid_payment_marks_one_off_event_booking_paid_with_paid_at(): void
    {
        $booking = OneOffEventBooking::factory()->create([
            'one_off_event_id' => OneOffEvent::factory()->create(['price' => 950])->getKey(),
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        $this->payFor($booking, 950);

        $booking->refresh();

        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
        $this->assertNotNull($booking->paid_at);
    }

    public function test_unpaid_payment_does_not_trigger(): void
    {
        $reservation = $this->reservation(800);

        $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 800,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        $this->assertSame(PaymentStatus::Unpaid, $reservation->fresh()->payment_status);
    }

    public function test_transition_to_paid_triggers(): void
    {
        $reservation = $this->reservation(800);

        $payment = $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 800,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->assertSame(PaymentStatus::Paid, $reservation->fresh()->payment_status);
    }

    public function test_partial_payments_accumulate_until_covered(): void
    {
        $reservation = $this->reservation(1000);

        $this->payFor($reservation, 400);

        $this->assertSame(PaymentStatus::Unpaid, $reservation->fresh()->payment_status);

        $this->payFor($reservation, 600);

        $this->assertSame(PaymentStatus::Paid, $reservation->fresh()->payment_status);
    }

    public function test_cancelled_reservation_settles_at_its_fee(): void
    {
        $reservation = $this->reservation(800, ReservationStatus::Cancelled);

        // The storno fee (50 % default) — far below the service price.
        $this->payFor($reservation, 400);

        $this->assertSame(PaymentStatus::Paid, $reservation->fresh()->payment_status);
    }

    public function test_already_paid_payable_keeps_original_paid_at(): void
    {
        $paidAt = now()->subDay()->startOfSecond();

        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => CourseSeries::factory()->create(['price' => 500])->getKey(),
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => $paidAt,
        ]);

        $this->payFor($enrollment, 500);

        $this->assertTrue($enrollment->fresh()->paid_at->equalTo($paidAt));
    }

    public function test_unmarking_or_deleting_payment_reverts_reservation(): void
    {
        // A reservation's payment_status is a deterministic cache of its payments,
        // so removing the covering payment reverts it to Unpaid.
        $reservation = $this->reservation(800);

        $payment = $this->payFor($reservation, 800);

        $this->assertSame(PaymentStatus::Paid, $reservation->fresh()->payment_status);

        $payment->update(['status' => PaymentStatus::Unpaid, 'paid_at' => null]);

        $this->assertSame(PaymentStatus::Unpaid, $reservation->fresh()->payment_status);

        $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->assertSame(PaymentStatus::Paid, $reservation->fresh()->payment_status);

        $payment->delete();

        $this->assertSame(PaymentStatus::Unpaid, $reservation->fresh()->payment_status);
    }

    public function test_unmarking_payment_does_not_revert_other_payables(): void
    {
        // Course enrollments (and the other non-reservation payables) keep the
        // forward-only auto-paid rule — un-marking a payment is a manual correction.
        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => CourseSeries::factory()->create(['price' => 500])->getKey(),
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        $payment = $this->payFor($enrollment, 500);

        $this->assertSame(PaymentStatus::Paid, $enrollment->fresh()->payment_status);

        $payment->update(['status' => PaymentStatus::Unpaid, 'paid_at' => null]);

        $this->assertSame(PaymentStatus::Paid, $enrollment->fresh()->payment_status);
    }

    private function reservation(int $price, ReservationStatus $status = ReservationStatus::Confirmed): Reservation
    {
        return Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => $price])->getKey(),
            'status' => $status,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
    }

    private function payFor(object $payable, int $amount): object
    {
        return $payable->payments()->create([
            'client_id' => $payable->client_id,
            'amount' => $amount,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
