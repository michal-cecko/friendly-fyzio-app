<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ReservationStornoPaymentNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ResolveDoctorNoteActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
    }

    private function doctorNoteReservation(int $price = 800): Reservation
    {
        return Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => $price])->getKey(),
            'status' => ReservationStatus::Cancelled,
            'payment_status' => PaymentStatus::Unpaid,
            'reservation_date' => today()->subDay()->toDateString(),
            'cancellation_reason' => 'Pozdní storno – potvrzení od lékaře',
            'doctor_note_requested_at' => now()->subDay(),
            'doctor_note_resolved_at' => null,
            'settled_at' => null,
        ]);
    }

    public function test_received_waives_fee_and_settles_without_charging(): void
    {
        Notification::fake();

        $reservation = $this->doctorNoteReservation();

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('resolveDoctorNote')->table($reservation), [
                'outcome' => 'received',
            ])
            ->assertHasNoActionErrors();

        $reservation->refresh();

        $this->assertNotNull($reservation->settled_at);
        $this->assertNotNull($reservation->doctor_note_resolved_at);
        // The outcome is preserved — it stays a cancellation, just a settled one.
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame(0, $reservation->payments()->count());
        Notification::assertNothingSent();
    }

    public function test_charge_raises_storno_payment_and_settles_once_paid(): void
    {
        Notification::fake();

        $reservation = $this->doctorNoteReservation(800);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('resolveDoctorNote')->table($reservation), [
                'outcome' => 'charge',
                'amount' => 400,
                'due_at' => today()->addDays(7)->toDateString(),
                'notify_client' => true,
            ])
            ->assertHasNoActionErrors();

        $reservation->refresh();

        $payment = $reservation->payments()->sole();
        $this->assertSame(400, $payment->amount);
        $this->assertSame(PaymentStatus::Unpaid, $payment->status);
        $this->assertSame(PaymentMethod::Qr, $payment->method);
        $this->assertNotNull($reservation->doctor_note_resolved_at);
        $this->assertNull($reservation->settled_at);

        Notification::assertSentTo($reservation->client, ReservationStornoPaymentNotification::class);

        // Paying the storno fee settles the reservation via the PaymentObserver.
        $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->assertNotNull($reservation->fresh()->settled_at);
    }

    public function test_action_hidden_without_a_pending_note(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
            'doctor_note_requested_at' => null,
        ]);

        Livewire::test(ListReservations::class)
            ->assertActionHidden(TestAction::make('resolveDoctorNote')->table($reservation));
    }

    public function test_action_hidden_once_resolved(): void
    {
        $reservation = $this->doctorNoteReservation();
        $reservation->update(['doctor_note_resolved_at' => now()]);

        Livewire::test(ListReservations::class)
            ->assertActionHidden(TestAction::make('resolveDoctorNote')->table($reservation));
    }
}
