<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateKey;
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

class CancelReservationActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_cancel_keeps_record_and_notifies_client(): void
    {
        Notification::fake();

        $reservation = $this->reservation();

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('cancelReservation')->table($reservation), [
                'cancellation_reason' => 'Terapeut nemocný',
                'force_delete' => false,
                'notify_client' => true,
            ])
            ->assertHasNoActionErrors();

        $reservation->refresh();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame('Terapeut nemocný', $reservation->cancellation_reason);
        $this->assertFalse($reservation->trashed());

        Notification::assertSentTo(
            $reservation->client,
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationCancelled,
        );
    }

    public function test_erase_opt_in_notifies_like_a_cancellation_and_moves_the_record_to_the_trash(): void
    {
        Notification::fake();

        $reservation = $this->reservation();
        $client = $reservation->client;

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('cancelReservation')->table($reservation), [
                'cancellation_reason' => 'Duplicitní rezervace',
                'force_delete' => true,
                'notify_client' => true,
            ])
            ->assertHasNoActionErrors();

        // The client got the ordinary cancellation notice; the reservation sits
        // in the trash, from where the daily prune erases it after 30 days.
        Notification::assertSentTo(
            $client,
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationCancelled,
        );

        $reservation = Reservation::withTrashed()->find($reservation->getKey());

        $this->assertTrue($reservation->trashed());
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
    }

    public function test_cancel_requires_a_reason(): void
    {
        $reservation = $this->reservation();

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('cancelReservation')->table($reservation), [
                'cancellation_reason' => null,
                'force_delete' => false,
                'notify_client' => false,
            ])
            ->assertHasActionErrors(['cancellation_reason' => 'required']);

        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);
    }

    private function reservation(): Reservation
    {
        return Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => 800])->getKey(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'reservation_date' => today()->addDay()->toDateString(),
        ]);
    }
}
