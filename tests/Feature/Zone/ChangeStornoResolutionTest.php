<?php

namespace Tests\Feature\Zone;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Livewire\Zone\ReservationDetail;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ReservationStornoPaymentNotification;
use App\Notifications\ReservationTemplateNotification;
use App\Support\Reservations\ClientReservationActions;
use App\Support\Reservations\ClientReservationState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A storno decision is not final. A client who promised a doctor's note but cannot
 * get one must be able to pay instead — and back again. The one exception is
 * deactivation: it blacklists the account, so it can never be changed online.
 */
class ChangeStornoResolutionTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->client = User::factory()->customer()->create(['email_verified_at' => now()]);
        $this->service = Service::factory()->create(['price' => 1000]);
    }

    private function cancelled(array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'reservation_date' => today()->subDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ReservationStatus::Cancelled,
            'payment_status' => PaymentStatus::Unpaid,
            ...$attributes,
        ]);
    }

    private function awaitingNote(): Reservation
    {
        return $this->cancelled([
            'cancellation_reason' => 'Pozdní storno – potvrzení od lékaře',
            'doctor_note_requested_at' => now()->subHour(),
        ]);
    }

    private function unpaidFee(): Reservation
    {
        $reservation = $this->cancelled(['cancellation_reason' => 'Pozdní storno – klient zaplatí']);

        $reservation->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 500,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        return $reservation;
    }

    public function test_a_promised_note_can_be_swapped_for_paying_the_fee(): void
    {
        $reservation = $this->awaitingNote();

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->assertSee('Změnit způsob vyřešení storna')
            ->call('openChangeStorno')
            ->assertSee('Potvrzení nedoložím, zaplatím storno')
            ->call('switchToStornoPayment')
            ->assertSet('changingStorno', false);

        $reservation->refresh();

        $this->assertSame(500, (int) $reservation->payments()->sole()->amount);
        // The note is no longer pending, so it drops off the staff work list.
        $this->assertNotNull($reservation->doctor_note_resolved_at);
        $this->assertSame('Pozdní storno – klient zaplatí', $reservation->cancellation_reason);
        $this->assertSame(
            ClientReservationState::CancelledUnpaid,
            ClientReservationState::for($reservation->load('payments', 'doctorNoteDocuments')),
        );

        Notification::assertSentTo($this->client, ReservationStornoPaymentNotification::class);
    }

    public function test_an_unpaid_fee_can_be_swapped_back_for_a_doctor_note(): void
    {
        $reservation = $this->unpaidFee();

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->call('openChangeStorno')
            ->call('switchToDoctorNote');

        $reservation->refresh();

        $this->assertNotNull($reservation->doctor_note_requested_at);
        $this->assertNull($reservation->doctor_note_resolved_at);
        // The withdrawn fee must not linger as an open debt — but it stays on record.
        $this->assertSame(0, $reservation->payments()->whereIn('status', PaymentStatus::openValues())->count());
        $this->assertSame(PaymentStatus::Cancelled, $reservation->payments()->sole()->status);
        $this->assertSame(
            ClientReservationState::AwaitingDoctorNote,
            ClientReservationState::for($reservation->load('payments', 'doctorNoteDocuments')),
        );

        Notification::assertSentTo(
            $this->client,
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationDoctorNote,
        );
    }

    public function test_refusing_to_pay_deactivates_and_is_final(): void
    {
        $reservation = $this->awaitingNote();

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->call('openChangeStorno')
            ->call('switchToDeactivation')
            ->assertSet('confirmation', 'deactivated');

        $reservation->refresh();

        $this->assertNotNull($this->client->fresh()->deactivated_at);
        $this->assertSame('Pozdní storno – bez úhrady', $reservation->cancellation_reason);
        $this->assertSame(0, $reservation->payments()->count());

        // Blacklisted: no further online change of heart.
        $this->assertFalse($reservation->canChangeStornoResolution());

        app(ClientReservationActions::class)->switchToStornoPayment($reservation);
        $this->assertSame(0, $reservation->fresh()->payments()->count());
    }

    public function test_a_settled_storno_can_no_longer_be_changed(): void
    {
        $reservation = $this->awaitingNote();
        $reservation->update(['doctor_note_resolved_at' => now(), 'settled_at' => now()]);

        $this->assertFalse($reservation->canChangeStornoResolution());

        app(ClientReservationActions::class)->switchToStornoPayment($reservation);

        $this->assertSame(0, $reservation->fresh()->payments()->count());
    }

    public function test_a_paid_storno_fee_is_never_withdrawn(): void
    {
        $reservation = $this->cancelled();
        $reservation->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 500,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->assertFalse($reservation->fresh()->canChangeStornoResolution());
    }

    public function test_the_change_is_available_through_the_signed_link_too(): void
    {
        $reservation = $this->awaitingNote();

        $this->post($reservation->doctorNoteUploadUrl(), ['action' => 'pay'])->assertRedirect();

        $this->assertSame(500, (int) $reservation->fresh()->payments()->sole()->amount);

        $this->post($reservation->doctorNoteUploadUrl(), ['action' => 'deactivate'])->assertRedirect();

        $this->assertNotNull($this->client->fresh()->deactivated_at);
        $this->assertSame(0, $reservation->payments()->whereIn('status', PaymentStatus::openValues())->count());
    }
}
