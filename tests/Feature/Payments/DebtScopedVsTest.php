<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\InvoiceSeries;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Support\Invoices\InvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The variable symbol identifies the DEBT: minted by the debt's first payment,
 * inherited by every further payment and by the invoice.
 */
class DebtScopedVsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        InvoiceSeries::factory()->asDefault()->create(['prefix' => 'FF']);
    }

    public function test_first_payment_mints_vs_from_its_number(): void
    {
        $payment = $this->payFor($this->reservation(), 400);

        $this->assertSame((string) $payment->number, $payment->variable_symbol);
    }

    public function test_second_payment_toward_same_debt_inherits_vs(): void
    {
        $reservation = $this->reservation();

        $first = $this->payFor($reservation, 400);
        $second = $this->payFor($reservation, 600);

        $this->assertSame($first->variable_symbol, $second->variable_symbol);
        $this->assertNotSame((string) $second->number, $second->variable_symbol);
    }

    public function test_post_invoice_payment_inherits_invoice_vs_and_auto_links(): void
    {
        $reservation = $this->reservation(price: 800);
        $this->payFor($reservation, 800, PaymentStatus::Paid);

        $invoice = app(InvoiceGenerator::class)->fromPayable($reservation->fresh());

        $extra = $this->payFor($reservation, 100, PaymentStatus::Paid);

        $extra->refresh();

        $this->assertSame($invoice->variable_symbol, $extra->variable_symbol);
        $this->assertSame($invoice->getKey(), $extra->invoice_id);
    }

    public function test_standalone_invoice_with_typed_vs_mirrors_it_to_the_backing_payment(): void
    {
        $generator = app(InvoiceGenerator::class);

        $invoice = $generator->create(
            InvoiceSeries::query()->sole(),
            User::factory()->customer()->create(),
            ['variable_symbol' => '9990001'],
            [['title' => 'Pronájem', 'unit_price' => 500]],
        );

        $backing = $generator->ensureBackingPayment($invoice);

        $this->assertSame('9990001', $backing->variable_symbol);
        $this->assertSame('9990001', $invoice->fresh()->variable_symbol);
    }

    public function test_standalone_invoice_with_blank_vs_receives_the_backing_payments_symbol(): void
    {
        $generator = app(InvoiceGenerator::class);

        $invoice = $generator->create(
            InvoiceSeries::query()->sole(),
            User::factory()->customer()->create(),
            [],
            [['title' => 'Pronájem', 'unit_price' => 500]],
        );

        $this->assertNull($invoice->variable_symbol);

        $backing = $generator->ensureBackingPayment($invoice);

        $this->assertSame((string) $backing->number, $backing->variable_symbol);
        $this->assertSame($backing->variable_symbol, $invoice->fresh()->variable_symbol);
    }

    public function test_unrelated_debts_never_share_a_vs(): void
    {
        $first = $this->payFor($this->reservation(), 400);
        $second = $this->payFor($this->reservation(), 400);

        $this->assertNotSame($first->variable_symbol, $second->variable_symbol);
    }

    private function reservation(int $price = 1000): Reservation
    {
        return Reservation::factory()->create([
            'service_id' => Service::factory()->create(['price' => $price])->getKey(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
    }

    private function payFor(Reservation $reservation, int $amount, PaymentStatus $status = PaymentStatus::Unpaid): Payment
    {
        return $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => $amount,
            'method' => PaymentMethod::Qr,
            'status' => $status,
            'paid_at' => $status === PaymentStatus::Paid ? now() : null,
        ]);
    }
}
