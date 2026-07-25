<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Pages;

use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Models\InvoiceSeries;
use App\Support\Invoices\DocumentNumberAllocator;
use App\Support\Invoices\InvoiceGenerator;
use App\Support\Invoices\SupplierSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The standalone-invoice path (no payable): the admin fills the client, items
 * and texts by hand; the number is allocated atomically with the insert and the
 * supplier identity is frozen from Settings. A backing Unpaid payment created
 * after the items persist gives the invoice its payment thread — and thereby
 * its variable symbol (the VS is never derived from the invoice number).
 */
class CreateInvoice extends BaseCreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static ?string $title = 'Nová faktura';

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $series = InvoiceSeries::query()->findOrFail($data['series_id']);

            $data['invoice_number'] = app(DocumentNumberAllocator::class)
                ->allocate($series, Carbon::parse($data['issued_at']));

            $data['supplier_snapshot'] = SupplierSnapshot::current();
            $data['amount'] ??= 0;

            return static::getModel()::create($data);
        });
    }

    protected function afterCreate(): void
    {
        // Runs after the items Repeater persisted, so the amount is final.
        app(InvoiceGenerator::class)->ensureBackingPayment($this->record->refresh());
    }
}
