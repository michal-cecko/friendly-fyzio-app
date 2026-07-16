<?php

namespace Tests\Feature;

use App\Enums\ConfirmationSource;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationTemplateNotification;
use App\Notifications\TherapistReservationTemplateNotification;
use Database\Seeders\EmailTemplateSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ReservationStaffActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->staff = User::factory()->admin()->create();
        $this->actingAs($this->staff);
    }

    private function reservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'status' => ReservationStatus::Pending,
            'reservation_date' => today()->addWeeks(3),
            'start_time' => '10:00',
            'end_time' => '11:00',
        ], $attributes));
    }

    public function test_staff_confirm_records_therapist_source_and_actor_and_emails_client(): void
    {
        Notification::fake();
        $this->seed(EmailTemplateSeeder::class);

        $reservation = $this->reservation();

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('confirmReservation')->table($reservation), [
                'notify_client' => true,
            ])
            ->assertHasNoActionErrors();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertNotNull($reservation->confirmed_at);
        $this->assertSame(ConfirmationSource::Therapist, $reservation->confirmed_by);
        $this->assertSame($this->staff->id, $reservation->confirmed_by_id);

        Notification::assertSentTo(
            $reservation->client,
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $n): bool => $n->key === EmailTemplateKey::ReservationConfirmed,
        );
    }

    public function test_staff_confirm_without_notify_sends_nothing(): void
    {
        Notification::fake();

        $reservation = $this->reservation();

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('confirmReservation')->table($reservation), [
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
        Notification::assertNothingSent();
    }

    public function test_unconfirm_reverts_to_pending_and_clears_confirmation(): void
    {
        $reservation = $this->reservation()->refresh();
        $reservation->update([
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => ConfirmationSource::Customer,
            'confirmed_by_id' => $reservation->client_id,
        ]);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('unconfirmReservation')->table($reservation))
            ->assertHasNoActionErrors();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Pending, $reservation->status);
        $this->assertNull($reservation->confirmed_at);
        $this->assertNull($reservation->confirmed_by);
        $this->assertNull($reservation->confirmed_by_id);
    }

    public function test_send_email_routes_client_vs_therapist_by_key(): void
    {
        Notification::fake();
        $this->seed(EmailTemplateSeeder::class);

        $reservation = $this->reservation();

        // A client-facing key → the client.
        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('sendReservationEmail')->table($reservation), [
                'template_key' => EmailTemplateKey::ReservationReminder->value,
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentTo(
            $reservation->client,
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $n): bool => $n->key === EmailTemplateKey::ReservationReminder,
        );

        // A therapist-facing key → the therapist's user.
        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('sendReservationEmail')->table($reservation), [
                'template_key' => EmailTemplateKey::TherapistReservationCreated->value,
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentTo(
            $reservation->therapist->user,
            TherapistReservationTemplateNotification::class,
            fn (TherapistReservationTemplateNotification $n): bool => $n->key === EmailTemplateKey::TherapistReservationCreated,
        );
    }

    public function test_send_email_offers_payment_keys_only_with_an_unpaid_payment(): void
    {
        Notification::fake();
        $this->seed(EmailTemplateSeeder::class);

        $reservation = $this->reservation();

        // No unpaid payment → the storno/unpaid keys are not selectable, so choosing one
        // is rejected by the Select's options validation.
        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('sendReservationEmail')->table($reservation), [
                'template_key' => EmailTemplateKey::ReservationUnpaid->value,
            ])
            ->assertHasActionErrors(['template_key']);

        Notification::assertNothingSent();

        // With an unpaid payment the key becomes available and sends with its amount.
        $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => 600,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('sendReservationEmail')->table($reservation), [
                'template_key' => EmailTemplateKey::ReservationUnpaid->value,
            ])
            ->assertHasNoActionErrors();

        Notification::assertSentTo(
            $reservation->client,
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $n): bool => $n->key === EmailTemplateKey::ReservationUnpaid,
        );
    }
}
