<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Moves unpaid invoices past their due date to "Po splatnosti". No e-mail here —
 * dunning happens at the payment level (payments:mark-overdue).
 */
class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Označí nezaplacené faktury po splatnosti.';

    public function handle(): int
    {
        $flipped = Invoice::query()
            ->whereIn('status', [InvoiceStatus::New->value, InvoiceStatus::Sent->value])
            ->whereDate('due_at', '<', today())
            ->update(['status' => InvoiceStatus::Overdue->value]);

        $this->info("Označeno {$flipped} faktur po splatnosti.");

        return self::SUCCESS;
    }
}
