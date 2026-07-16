<?php

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceItemRecalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_item_computes_total_from_quantity_and_unit_price(): void
    {
        $item = InvoiceItem::factory()->create([
            'quantity' => 3,
            'unit_price' => 200,
            'total' => 0,
        ]);

        $this->assertSame(600, $item->fresh()->total);
    }

    public function test_item_changes_recalculate_invoice_amount(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 0]);

        $first = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->getKey(),
            'quantity' => 1,
            'unit_price' => 800,
        ]);

        $second = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->getKey(),
            'quantity' => 2,
            'unit_price' => 250,
        ]);

        $this->assertSame(1300, $invoice->fresh()->amount);

        $second->update(['unit_price' => 300]);

        $this->assertSame(1400, $invoice->fresh()->amount);

        $first->delete();

        $this->assertSame(600, $invoice->fresh()->amount);
    }
}
