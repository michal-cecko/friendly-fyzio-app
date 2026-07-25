<?php

namespace App\Filament\Clusters\Finance\Resources\InvoiceSeries\Pages;

use App\Filament\Clusters\Finance\Resources\InvoiceSeries\InvoiceSeriesResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Models\InvoiceSeries;
use Filament\Actions\DeleteAction;

class EditInvoiceSeries extends BaseEditRecord
{
    protected static string $resource = InvoiceSeriesResource::class;

    public function getTitle(): string
    {
        /** @var InvoiceSeries $record */
        $record = $this->getRecord();

        return 'Upravit číselnou řadu '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (InvoiceSeries $record): bool => ! $record->invoices()->exists()
                    && ! $record->cashReceipts()->exists()),
        ];
    }
}
