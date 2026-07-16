<?php

namespace App\Filament\Clusters\Finance\Resources\InvoiceSeries\Pages;

use App\Filament\Clusters\Finance\Resources\InvoiceSeries\InvoiceSeriesResource;
use App\Models\InvoiceSeries;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInvoiceSeries extends EditRecord
{
    protected static string $resource = InvoiceSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (InvoiceSeries $record): bool => ! $record->invoices()->exists()
                    && ! $record->cashReceipts()->exists()),
        ];
    }
}
