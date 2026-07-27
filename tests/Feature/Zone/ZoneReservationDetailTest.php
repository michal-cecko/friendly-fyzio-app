<?php

namespace Tests\Feature\Zone;

use App\Enums\ConfirmationSource;
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
use App\Support\Reservations\ClientReservationState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ZoneReservationDetailTest extends TestCase
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

    private function reservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'reservation_date' => today()->addDays(10)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            ...$attributes,
        ]);
    }

    public function test_state_is_derived_for_every_client_facing_case(): void
    {
        $pending = $this->reservation(['status' => ReservationStatus::Pending]);
        $this->assertSame(ClientReservationState::Pending, ClientReservationState::for($pending));

        $confirmed = $this->reservation();
        $this->assertSame(ClientReservationState::Confirmed, ClientReservationState::for($confirmed));

        $cancelled = $this->reservation(['status' => ReservationStatus::Cancelled]);
        $this->assertSame(ClientReservationState::Cancelled, ClientReservationState::for($cancelled));

        $completed = $this->reservation([
            'reservation_date' => today()->subWeek()->toDateString(),
            'payment_status' => PaymentStatus::Paid,
        ]);
        $this->assertSame(ClientReservationState::Completed, ClientReservationState::for($completed->load('payments')));

        // Past + unpaid + no payment row = pay on site.
        $cash = $this->reservation(['reservation_date' => today()->subWeek()->toDateString()]);
        $this->assertSame(ClientReservationState::AwaitingCash, ClientReservationState::for($cash->load('payments')));

        $qr = $this->reservation(['reservation_date' => today()->subWeek()->toDateString()]);
        $qr->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 500,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);
        $this->assertSame(ClientReservationState::AwaitingQr, ClientReservationState::for($qr->load('payments')));

        $credit = $this->reservation(['reservation_date' => today()->subWeek()->toDateString()]);
        $credit->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 500,
            'method' => PaymentMethod::Credit,
            'status' => PaymentStatus::Unpaid,
        ]);
        $this->assertSame(ClientReservationState::AwaitingCredit, ClientReservationState::for($credit->load('payments')));
    }

    public function test_the_detail_page_shows_the_qr_panel_for_an_open_request(): void
    {
        $reservation = $this->reservation(['reservation_date' => today()->subWeek()->toDateString()]);
        $payment = $reservation->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 500,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->assertSee('Způsob platby')
            ->assertSee($payment->variable_symbol);
    }

    public function test_a_free_cancel_cancels_without_a_fee(): void
    {
        // Pending + outside the window = free.
        $reservation = $this->reservation(['status' => ReservationStatus::Pending]);

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->call('openCancel')
            ->assertSee('Zrušit rezervaci?')
            ->call('cancelFree');

        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
        $this->assertSame(0, $reservation->payments()->count());

        Notification::assertSentTo($this->client, ReservationTemplateNotification::class, fn (ReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationCancelled);
    }

    public function test_a_late_cancel_offers_the_storno_decision_and_can_raise_the_fee(): void
    {
        // Confirmed = storno decision required (the fee applies).
        $reservation = $this->reservation();

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->call('openCancel')
            ->assertSee('Vyberte způsob zrušení')
            ->call('cancelAndPay');

        $reservation->refresh();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame(500, (int) $reservation->payments()->sole()->amount);

        Notification::assertSentTo($this->client, ReservationStornoPaymentNotification::class);
    }

    public function test_refusing_to_pay_deactivates_the_account_and_shows_the_confirmation(): void
    {
        $reservation = $this->reservation();

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->call('openCancel')
            ->call('cancelAndDeactivate')
            ->assertSet('confirmation', 'deactivated')
            ->assertSee('Účet byl deaktivován');

        $this->assertNotNull($this->client->fresh()->deactivated_at);
    }

    public function test_paying_the_storno_fee_shows_the_confirmation_screen(): void
    {
        $reservation = $this->reservation();

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->call('openCancel')
            ->call('cancelAndPay')
            ->assertSet('confirmation', 'storno_paid')
            ->assertSee('Rezervace zrušena');
    }

    public function test_a_doctor_note_cancel_waives_the_fee(): void
    {
        $reservation = $this->reservation();

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->call('openCancel')
            ->call('cancelWithDoctorNote')
            ->assertSet('confirmation', 'doctor_note')
            // The result screen sends the client on to the upload, not to a mailto.
            ->assertSee('Nahrát potvrzení')
            ->call('showDetail')
            ->assertSet('confirmation', null)
            ->assertSee('Potvrzení od lékaře')
            ->assertSee('Čeká na potvrzení od lékaře');

        $reservation->refresh();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertNotNull($reservation->doctor_note_requested_at);
        $this->assertSame(0, $reservation->payments()->count());
    }

    /**
     * A cancellation is not automatically closed: a suspended note or an unpaid fee
     * must keep saying so instead of collapsing into a bare „Stornováno".
     */
    public function test_an_unresolved_cancellation_keeps_its_own_state(): void
    {
        $awaitingNote = $this->reservation([
            'status' => ReservationStatus::Cancelled,
            'doctor_note_requested_at' => now(),
        ]);
        $this->assertSame(
            ClientReservationState::AwaitingDoctorNote,
            ClientReservationState::for($awaitingNote->load('payments', 'doctorNoteDocuments')),
        );

        $unpaidStorno = $this->reservation(['status' => ReservationStatus::Cancelled]);
        $unpaidStorno->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 500,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);
        $this->assertSame(
            ClientReservationState::CancelledUnpaid,
            ClientReservationState::for($unpaidStorno->load('payments', 'doctorNoteDocuments')),
        );
    }

    public function test_a_cancelled_reservation_offers_no_actions(): void
    {
        $reservation = $this->reservation([
            'status' => ReservationStatus::Cancelled,
            'doctor_note_requested_at' => now(),
        ]);

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->assertDontSee('Zrušit rezervaci')
            ->assertDontSee('Přesunout termín');
    }

    public function test_a_pending_reservation_can_be_confirmed_from_the_zone(): void
    {
        $reservation = $this->reservation(['status' => ReservationStatus::Pending]);

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->call('openConfirm')
            ->assertSee('Potvrdit rezervaci?')
            ->call('confirmReservation')
            ->assertSet('confirmingConfirm', false);

        $reservation->refresh();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertNotNull($reservation->confirmed_at);
        $this->assertSame(ConfirmationSource::Customer, $reservation->confirmed_by);

        Notification::assertSentTo(
            $this->client,
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationConfirmed,
        );
    }

    public function test_the_confirm_action_is_offered_only_while_pending(): void
    {
        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $this->reservation(['status' => ReservationStatus::Pending])])
            ->assertSee('Akce')
            ->assertSee('Potvrdit rezervaci');

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $this->reservation()])
            ->assertSee('Akce')
            ->assertDontSee('Potvrdit rezervaci');
    }

    public function test_the_confirmed_timestamp_is_shown(): void
    {
        $reservation = $this->reservation(['confirmed_at' => Carbon::parse('2026-03-04 15:45')]);

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->assertSee('Potvrzeno 4. 3. 2026 · 15:45');
    }

    public function test_another_clients_reservation_is_not_reachable(): void
    {
        $reservation = $this->reservation();
        $stranger = User::factory()->customer()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)
            ->get(route('zone.reservations.show', $reservation))
            ->assertNotFound();
    }
}
