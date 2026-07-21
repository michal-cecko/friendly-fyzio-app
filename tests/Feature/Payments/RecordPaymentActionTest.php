<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\ListCourseEnrollments;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages\ListOneOffEventBookings;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\InvoiceSeries;
use App\Models\OneOffEvent;
use App\Models\OneOffEventBooking;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RecordPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_recording_received_payment_on_reservation_marks_it_paid(): void
    {
        Notification::fake();

        $reservation = $this->reservation(800);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('recordPayment')->table($reservation), [
                'amount' => 800,
                'method' => PaymentMethod::Qr->value,
                'received' => true,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $reservation->refresh();

        $this->assertSame(PaymentStatus::Paid, $reservation->payment_status);
        $this->assertSame(1, $reservation->payments()->count());
        // The toggle suppresses only the client copy — the therapist notice still goes out.
        Notification::assertNotSentTo($reservation->client, PaymentReceivedNotification::class);
    }

    public function test_cash_received_payment_auto_creates_cash_receipt(): void
    {
        InvoiceSeries::factory()->receipt()->asDefault()->create(['prefix' => 'PPD']);

        $reservation = $this->reservation(800);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('recordPayment')->table($reservation), [
                'amount' => 800,
                'method' => PaymentMethod::Cash->value,
                'received' => true,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $payment = $reservation->payments()->sole();

        $this->assertNotNull($payment->cashReceipt()->first());
        $this->assertStringStartsWith('PPD-', $payment->cashReceipt()->first()->receipt_number);
    }

    public function test_received_off_creates_unpaid_payment_with_due_date(): void
    {
        $reservation = $this->reservation(800);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('recordPayment')->table($reservation), [
                'amount' => 800,
                'method' => PaymentMethod::Qr->value,
                'received' => false,
            ])
            ->assertHasNoActionErrors();

        $payment = $reservation->payments()->sole();

        $this->assertSame(PaymentStatus::Unpaid, $payment->status);
        $this->assertTrue($payment->due_at->isSameDay(today()->addDays(7)));
        $this->assertSame(PaymentStatus::Unpaid, $reservation->fresh()->payment_status);
    }

    public function test_notify_toggle_sends_payment_received_email(): void
    {
        Notification::fake();

        $reservation = $this->reservation(800);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('recordPayment')->table($reservation), [
                'amount' => 800,
                'method' => PaymentMethod::Qr->value,
                'received' => true,
                'notify_client' => true,
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentTo($reservation->client, PaymentReceivedNotification::class);
    }

    public function test_action_hidden_for_paid_payable(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
        ]);

        Livewire::test(ListReservations::class)
            ->assertActionHidden(TestAction::make('recordPayment')->table($reservation));
    }

    public function test_records_payment_on_course_enrollment(): void
    {
        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => CourseSeries::factory()->create(['price' => 1200])->getKey(),
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        Livewire::test(ListCourseEnrollments::class)
            ->callAction(TestAction::make('recordPayment')->table($enrollment), [
                'amount' => 1200,
                'method' => PaymentMethod::Qr->value,
                'received' => true,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(PaymentStatus::Paid, $enrollment->fresh()->payment_status);
    }

    public function test_records_payment_on_one_off_event_booking(): void
    {
        $booking = OneOffEventBooking::factory()->create([
            'one_off_event_id' => OneOffEvent::factory()->create(['price' => 950])->getKey(),
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        Livewire::test(ListOneOffEventBookings::class)
            ->callAction(TestAction::make('recordPayment')->table($booking), [
                'amount' => 950,
                'method' => PaymentMethod::Qr->value,
                'received' => true,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(PaymentStatus::Paid, $booking->fresh()->payment_status);
    }

    private function reservation(int $price): Reservation
    {
        return Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => $price])->getKey(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
    }
}
