<?php

namespace Tests\Feature\CashReceipts;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSeries;
use App\Models\Payment;
use App\Support\Invoices\CashReceiptGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CashReceiptGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private CashReceiptGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(CashReceiptGenerator::class);

        InvoiceSeries::factory()->receipt()->asDefault()->create(['prefix' => 'PPD']);
    }

    public function test_from_cash_paid_payment_allocates_receipt_series_number(): void
    {
        $payment = Payment::factory()->cash()->paid()->create(['amount' => 500]);

        $receipt = $this->generator->fromPayment($payment);

        $this->assertSame('PPD-'.today()->year.'-00001', $receipt->receipt_number);
        $this->assertSame($payment->getKey(), $receipt->payment_id);
        $this->assertNull($receipt->invoice_id);
        $this->assertSame(500, $receipt->amount);
        $this->assertSame($payment->client->name, $receipt->client_name);
        $this->assertTrue($receipt->received_at->isSameDay($payment->paid_at));
        $this->assertSame('Platba č. '.$payment->number, $receipt->purpose);
    }

    public function test_from_invoice_links_invoice_and_amount(): void
    {
        $invoice = Invoice::factory()->create(['payment_method' => PaymentMethod::Cash]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->getKey(), 'unit_price' => 600]);

        $receipt = $this->generator->fromInvoice($invoice->fresh());

        $this->assertSame($invoice->getKey(), $receipt->invoice_id);
        $this->assertSame(600, $receipt->amount);
        $this->assertSame('Úhrada faktury č. '.$invoice->invoice_number, $receipt->purpose);
        $this->assertSame($invoice->client_snapshot['name'], $receipt->client_name);
    }

    public function test_refuses_non_cash_payment(): void
    {
        $payment = Payment::factory()->paid()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->generator->fromPayment($payment);
    }

    public function test_refuses_unpaid_cash_payment(): void
    {
        $payment = Payment::factory()->cash()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->generator->fromPayment($payment);
    }

    public function test_duplicate_generation_returns_existing_receipt(): void
    {
        $payment = Payment::factory()->cash()->paid()->create();

        $first = $this->generator->fromPayment($payment);
        $second = $this->generator->fromPayment($payment->fresh());

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('cash_receipts', 1);
    }
}
