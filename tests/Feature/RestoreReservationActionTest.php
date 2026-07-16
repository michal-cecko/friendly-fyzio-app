<?php

namespace Tests\Feature;

use App\Enums\ConfirmationSource;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ReservationTemplateNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RestoreReservationActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_restoring_a_trashed_reservation_outside_the_window_makes_it_pending_and_thanks_the_client(): void
    {
        Notification::fake();

        $reservation = $this->cancelledReservation(daysAhead: 10, trashed: true);
        $client = $reservation->client;

        Livewire::test(ListReservations::class)
            ->filterTable('trashed', true)
            ->callAction(TestAction::make('restoreReservation')->table($reservation), [
                'notify_client' => true,
            ])
            ->assertHasNoActionErrors();

        $reservation->refresh();

        $this->assertFalse($reservation->trashed());
        $this->assertSame(ReservationStatus::Pending, $reservation->status);
        $this->assertNull($reservation->cancellation_reason);
        $this->assertNull($reservation->confirmed_at);
        $this->assertNull($reservation->confirmed_by);
        $this->assertNull($reservation->confirmed_by_id);
        // Cleared markers re-arm the standard confirmation-request + reminder crons.
        $this->assertNull($reservation->confirmation_sent_at);
        $this->assertNull($reservation->reminder_sent_at);

        Notification::assertSentTo(
            $client,
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationCreated,
        );
    }

    public function test_restoring_inside_the_confirmation_window_auto_confirms(): void
    {
        Notification::fake();

        $reservation = $this->cancelledReservation(daysAhead: 1);
        $client = $reservation->client;

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('restoreReservation')->table($reservation), [
                'notify_client' => true,
            ])
            ->assertHasNoActionErrors();

        $reservation->refresh();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame(ConfirmationSource::Automatic, $reservation->confirmed_by);
        $this->assertNotNull($reservation->confirmed_at);

        Notification::assertSentTo(
            $client,
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationAutoConfirmed,
        );
    }

    public function test_a_cancelled_but_kept_record_can_be_reactivated_without_notifying(): void
    {
        Notification::fake();

        $reservation = $this->cancelledReservation(daysAhead: 10);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('restoreReservation')->table($reservation), [
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);

        Notification::assertNothingSent();
    }

    public function test_restore_aborts_when_the_slot_is_meanwhile_occupied(): void
    {
        Notification::fake();

        $reservation = $this->cancelledReservation(daysAhead: 10);

        Reservation::factory()->create([
            'therapist_id' => $reservation->therapist_id,
            'reservation_date' => $reservation->reservation_date->toDateString(),
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'status' => ReservationStatus::Confirmed,
        ]);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('restoreReservation')->table($reservation), [
                'notify_client' => true,
            ]);

        $reservation->refresh();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame('Zrušeno klientem', $reservation->cancellation_reason);

        Notification::assertNothingSent();
    }

    public function test_open_unpaid_payments_are_kept_on_restore(): void
    {
        Notification::fake();

        $reservation = $this->cancelledReservation(daysAhead: 10);

        $payment = $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 400,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('restoreReservation')->table($reservation), [
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
        // User decision: restore never touches payments — staff clean up in Finance.
        $this->assertDatabaseHas('payments', ['id' => $payment->getKey(), 'status' => PaymentStatus::Unpaid->value]);
    }

    private function cancelledReservation(int $daysAhead, bool $trashed = false): Reservation
    {
        $reservation = Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => 800])->getKey(),
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Zrušeno klientem',
            'payment_status' => PaymentStatus::Unpaid,
            'reservation_date' => today()->addDays($daysAhead)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'confirmed_at' => now()->subDay(),
            'confirmed_by' => ConfirmationSource::Customer,
            'confirmation_sent_at' => now()->subDays(2),
            'reminder_sent_at' => now()->subDay(),
        ]);

        if ($trashed) {
            $reservation->delete();
        }

        return $reservation;
    }
}
