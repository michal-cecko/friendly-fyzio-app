<?php

namespace Tests\Feature\Zone;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Livewire\Zone\Payments;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Zaplatit" modal on the client's payments page: transfer instructions for
 * an open bank transfer, the cash → transfer switch, and the credit note.
 */
class ZonePaymentsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_open_transfer_shows_the_bank_details(): void
    {
        $payment = $this->payment(PaymentMethod::Qr, PaymentStatus::Unpaid);

        Livewire::actingAs($payment->client)
            ->test(Payments::class)
            ->assertSee('Zaplatit')
            ->call('openPayment', $payment->getKey())
            ->assertSee('Platba převodem')
            ->assertSee('Variabilní symbol')
            ->assertSee($payment->variable_symbol);
    }

    public function test_a_cash_payment_offers_the_switch_to_a_transfer_first(): void
    {
        $payment = $this->payment(PaymentMethod::Cash, PaymentStatus::Unpaid);

        Livewire::actingAs($payment->client)
            ->test(Payments::class)
            ->call('openPayment', $payment->getKey())
            ->assertSee('Přejete si změnit způsob platby na bankovní převod?')
            ->assertDontSee('Variabilní symbol')
            ->call('switchToTransfer')
            ->assertSee('Platba převodem')
            ->assertSee('Variabilní symbol');

        $this->assertSame(PaymentMethod::Qr, $payment->fresh()->method);
    }

    public function test_a_credit_payment_points_the_client_at_their_therapist(): void
    {
        $payment = $this->payment(PaymentMethod::Credit, PaymentStatus::Unpaid);

        Livewire::actingAs($payment->client)
            ->test(Payments::class)
            ->call('openPayment', $payment->getKey())
            ->assertSee('Kredit odečítá váš terapeut')
            ->call('switchToTransfer');

        $this->assertSame(PaymentMethod::Credit, $payment->fresh()->method);
    }

    public function test_a_settled_payment_keeps_its_method_and_offers_the_invoice(): void
    {
        $payment = $this->payment(PaymentMethod::Cash, PaymentStatus::Paid);

        Livewire::actingAs($payment->client)
            ->test(Payments::class)
            ->assertSee('Stáhnout fakturu')
            ->call('openPayment', $payment->getKey())
            ->call('switchToTransfer');

        $this->assertSame(PaymentMethod::Cash, $payment->fresh()->method);
    }

    public function test_the_pay_modal_opens_from_a_deep_link(): void
    {
        $payment = $this->payment(PaymentMethod::Qr, PaymentStatus::Unpaid);

        Livewire::actingAs($payment->client)
            ->withQueryParams(['platba' => $payment->getKey()])
            ->test(Payments::class)
            ->assertSet('payingId', $payment->getKey())
            ->assertSee('Platba převodem')
            ->assertSee($payment->variable_symbol);
    }

    public function test_another_clients_payment_cannot_be_opened_or_switched(): void
    {
        $payment = $this->payment(PaymentMethod::Cash, PaymentStatus::Unpaid);
        $stranger = User::factory()->customer()->create(['email_verified_at' => now()]);

        Livewire::actingAs($stranger)
            ->test(Payments::class)
            ->call('openPayment', $payment->getKey())
            ->assertDontSee('Přejete si změnit způsob platby')
            ->call('switchToTransfer');

        $this->assertSame(PaymentMethod::Cash, $payment->fresh()->method);
    }

    private function payment(PaymentMethod $method, PaymentStatus $status): Payment
    {
        $client = User::factory()->customer()->create(['email_verified_at' => now()]);

        $reservation = Reservation::factory()->create([
            'client_id' => $client->getKey(),
            'service_id' => Service::factory()->create(['price' => 800])->getKey(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        return $reservation->payments()->create([
            'client_id' => $client->getKey(),
            'amount' => 800,
            'method' => $method,
            'status' => $status,
            'paid_at' => $status === PaymentStatus::Paid ? now() : null,
            'due_at' => today()->addWeek(),
        ]);
    }
}
