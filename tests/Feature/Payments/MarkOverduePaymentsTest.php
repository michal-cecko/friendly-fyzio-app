<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Service;
use App\Notifications\PaymentOverdueNotification;
use App\Notifications\TherapistPaymentOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MarkOverduePaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_flips_and_notifies_once_for_overdue_payments(): void
    {
        Notification::fake();

        $reservation = Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => 800])->getKey(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        $payment = $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 800,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
            'due_at' => today()->subDays(3),
        ]);

        $this->artisan('payments:mark-overdue')->assertSuccessful();

        $payment->refresh();

        $this->assertSame(PaymentStatus::Overdue, $payment->status);
        $this->assertNotNull($payment->overdue_notified_at);

        // The reservation cache is derived from the payment, so it flips too.
        $this->assertSame(PaymentStatus::Overdue, $reservation->fresh()->payment_status);

        Notification::assertSentTo($reservation->client, PaymentOverdueNotification::class);
        Notification::assertSentTo($reservation->therapist->user, TherapistPaymentOverdueNotification::class);

        // Second run: nothing new goes out.
        $this->artisan('payments:mark-overdue')->assertSuccessful();

        Notification::assertSentToTimes($reservation->client, PaymentOverdueNotification::class, 1);
    }

    public function test_skips_future_and_paid_payments(): void
    {
        Notification::fake();

        $future = Payment::factory()->create(['due_at' => today()->addDays(3)]);
        $paid = Payment::factory()->paid()->create(['due_at' => today()->subDays(3)]);
        $noDue = Payment::factory()->create();

        $this->artisan('payments:mark-overdue')->assertSuccessful();

        $this->assertSame(PaymentStatus::Unpaid, $future->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $paid->fresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $noDue->fresh()->status);

        Notification::assertNothingSent();
    }

    public function test_overdue_payment_still_settles_payable_when_paid(): void
    {
        $reservation = Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => 800])->getKey(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        $payment = $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 800,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
            'due_at' => today()->subDays(3),
        ]);

        $this->artisan('payments:mark-overdue')->assertSuccessful();

        $payment->fresh()->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->assertSame(PaymentStatus::Paid, $reservation->fresh()->payment_status);
    }
}
