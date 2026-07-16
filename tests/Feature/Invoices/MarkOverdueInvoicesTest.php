<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkOverdueInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_new_and_sent_invoices_past_due(): void
    {
        $new = Invoice::factory()->create(['due_at' => today()->subDays(2)]);
        $sent = Invoice::factory()->sent()->create(['due_at' => today()->subDay()]);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame(InvoiceStatus::Overdue, $new->fresh()->status);
        $this->assertSame(InvoiceStatus::Overdue, $sent->fresh()->status);
    }

    public function test_leaves_paid_and_future_invoices_untouched(): void
    {
        $paid = Invoice::factory()->paid()->create(['due_at' => today()->subDays(5)]);
        $future = Invoice::factory()->create(['due_at' => today()->addDays(5)]);
        $dueToday = Invoice::factory()->create(['due_at' => today()]);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame(InvoiceStatus::Paid, $paid->fresh()->status);
        $this->assertSame(InvoiceStatus::New, $future->fresh()->status);
        $this->assertSame(InvoiceStatus::New, $dueToday->fresh()->status);
    }
}
