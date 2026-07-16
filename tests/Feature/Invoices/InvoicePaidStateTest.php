<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\InvoiceSeries;
use App\Models\User;
use App\Support\Invoices\InvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Standalone invoices derive their paid state (forward-only) from the linked
 * payment thread, and a lone unpaid backing payment mirrors invoice edits.
 */
class InvoicePaidStateTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(InvoiceGenerator::class);

        InvoiceSeries::factory()->asDefault()->create(['prefix' => 'FF']);
    }

    public function test_backing_payment_marked_paid_flips_invoice_to_paid(): void
    {
        $invoice = $this->standaloneInvoice(1000);
        $backing = $this->generator->ensureBackingPayment($invoice);

        $this->assertSame(InvoiceStatus::New, $invoice->fresh()->status);

        $backing->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_partial_payments_flip_only_once_covered(): void
    {
        $invoice = $this->standaloneInvoice(1000);
        $backing = $this->generator->ensureBackingPayment($invoice);

        // First installment arrives: shrink the backing payment and mark it paid.
        $backing->update(['amount' => 400, 'status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->assertSame(InvoiceStatus::New, $invoice->fresh()->status);

        // Second transfer covers the rest.
        $invoice->payments()->create([
            'client_id' => $invoice->client_id,
            'amount' => 600,
            'method' => $invoice->payment_method,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_unmarking_or_deleting_a_payment_never_reverts_the_invoice(): void
    {
        $invoice = $this->standaloneInvoice(1000);
        $backing = $this->generator->ensureBackingPayment($invoice);

        $backing->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $backing->update(['status' => PaymentStatus::Unpaid, 'paid_at' => null]);

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $backing->delete();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_item_edits_sync_the_lone_unpaid_backing_payment(): void
    {
        $invoice = $this->standaloneInvoice(1000);
        $backing = $this->generator->ensureBackingPayment($invoice);

        $this->assertSame(1000, $backing->amount);

        $invoice->items->sole()->update(['unit_price' => 1500]);

        $backing->refresh();

        $this->assertSame(1500, $backing->amount);
        $this->assertTrue($backing->due_at->isSameDay($invoice->due_at));
    }

    public function test_item_edits_never_touch_paid_or_multi_payment_threads(): void
    {
        $invoice = $this->standaloneInvoice(1000);
        $backing = $this->generator->ensureBackingPayment($invoice);

        $backing->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $invoice->items->sole()->update(['unit_price' => 1500]);

        $this->assertSame(1000, $backing->fresh()->amount);
    }

    private function standaloneInvoice(int $unitPrice): Invoice
    {
        return $this->generator->create(
            InvoiceSeries::query()->sole(),
            User::factory()->customer()->create(),
            [],
            [['title' => 'Pronájem tělocvičny', 'unit_price' => $unitPrice]],
        );
    }
}
