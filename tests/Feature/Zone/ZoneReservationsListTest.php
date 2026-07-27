<?php

namespace Tests\Feature\Zone;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Livewire\Zone\Reservations;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The two tabs of "Moje rezervace". The rule under test: „Aktivní" holds what is
 * still coming up PLUS anything the client has to resolve (unpaid fee, promised
 * doctor's note), no matter how far in the past it is. Only genuinely closed
 * reservations belong in „Dokončené".
 */
class ZoneReservationsListTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function tab(string $tab): Testable
    {
        return Livewire::actingAs($this->client)
            ->test(Reservations::class)
            ->call('selectTab', $tab);
    }

    public function test_an_upcoming_reservation_is_active(): void
    {
        $reservation = $this->reservation();

        $this->tab('aktivni')
            ->assertViewHas('reservations', fn ($rows): bool => $rows->contains($reservation));

        $this->tab('dokoncene')
            ->assertViewHas('reservations', fn ($rows): bool => $rows->isEmpty());
    }

    public function test_a_cancellation_awaiting_a_doctor_note_stays_active_even_when_past(): void
    {
        $reservation = $this->reservation([
            'reservation_date' => today()->subWeek()->toDateString(),
            'status' => ReservationStatus::Cancelled,
            'doctor_note_requested_at' => now()->subDay(),
        ]);

        $this->tab('aktivni')
            ->assertViewHas('attention', fn ($rows): bool => $rows->contains($reservation))
            ->assertSee('Vyžaduje vaši pozornost')
            ->assertSee('Čeká na potvrzení od lékaře');

        // And it is NOT duplicated into the finished tab.
        $this->tab('dokoncene')
            ->assertViewHas('attention', fn ($rows): bool => $rows->isEmpty())
            ->assertViewHas('reservations', fn ($rows): bool => $rows->isEmpty());
    }

    public function test_a_cancellation_with_an_unpaid_storno_fee_stays_active(): void
    {
        $reservation = $this->reservation([
            'reservation_date' => today()->subWeek()->toDateString(),
            'status' => ReservationStatus::Cancelled,
        ]);
        $reservation->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 500,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        $this->tab('aktivni')
            ->assertViewHas('attention', fn ($rows): bool => $rows->contains($reservation))
            ->assertSee('Stornováno – čeká na úhradu');
    }

    public function test_a_free_cancellation_is_finished(): void
    {
        $reservation = $this->reservation([
            'reservation_date' => today()->subWeek()->toDateString(),
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Zrušeno klientem',
        ]);

        $this->tab('dokoncene')
            ->assertViewHas('reservations', fn ($rows): bool => $rows->contains($reservation));

        $this->tab('aktivni')
            ->assertViewHas('attention', fn ($rows): bool => $rows->isEmpty())
            ->assertViewHas('reservations', fn ($rows): bool => $rows->isEmpty());
    }

    public function test_a_past_paid_visit_is_finished(): void
    {
        $reservation = $this->reservation([
            'reservation_date' => today()->subWeek()->toDateString(),
            'payment_status' => PaymentStatus::Paid,
            'settled_at' => now(),
        ]);
        $reservation->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 1000,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->tab('dokoncene')
            ->assertViewHas('reservations', fn ($rows): bool => $rows->contains($reservation));
    }

    public function test_a_past_visit_with_an_open_payment_row_is_active(): void
    {
        $reservation = $this->reservation(['reservation_date' => today()->subWeek()->toDateString()]);
        $reservation->payments()->create([
            'client_id' => $this->client->id,
            'amount' => 1000,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        $this->tab('aktivni')
            ->assertViewHas('attention', fn ($rows): bool => $rows->contains($reservation));
    }

    /**
     * A past visit that was never marked paid reads as „Čeká na platbu (hotově)".
     * A row saying that must never sit in the finished tab — the tab and the badge
     * are driven by the same rule.
     */
    public function test_a_past_visit_never_marked_paid_is_active(): void
    {
        $reservation = $this->reservation(['reservation_date' => today()->subWeek()->toDateString()]);

        $this->tab('aktivni')
            ->assertViewHas('attention', fn ($rows): bool => $rows->contains($reservation))
            ->assertSee('Čeká na platbu (hotově)');

        $this->tab('dokoncene')
            ->assertViewHas('attention', fn ($rows): bool => $rows->isEmpty())
            ->assertViewHas('reservations', fn ($rows): bool => $rows->isEmpty())
            ->assertDontSee('Čeká na platbu');
    }

    /**
     * The settled marker ("Vybaveno") closes a visit even when nothing was owed and
     * the cached payment_status was never refreshed.
     */
    public function test_a_settled_visit_is_finished(): void
    {
        $reservation = $this->reservation([
            'reservation_date' => today()->subWeek()->toDateString(),
            'payment_status' => PaymentStatus::Unpaid,
            'settled_at' => now(),
        ]);

        $this->tab('dokoncene')
            ->assertViewHas('reservations', fn ($rows): bool => $rows->contains($reservation))
            ->assertSee('Dokončeno');
    }

    public function test_legacy_tab_names_still_resolve(): void
    {
        Livewire::actingAs($this->client)
            ->withQueryParams(['zalozka' => 'minule'])
            ->test(Reservations::class)
            ->assertSet('tab', 'dokoncene');

        Livewire::actingAs($this->client)
            ->withQueryParams(['zalozka' => 'nadchazejici'])
            ->test(Reservations::class)
            ->assertSet('tab', 'aktivni');

        Livewire::actingAs($this->client)
            ->withQueryParams(['zalozka' => 'nesmysl'])
            ->test(Reservations::class)
            ->assertSet('tab', 'aktivni');
    }
}
