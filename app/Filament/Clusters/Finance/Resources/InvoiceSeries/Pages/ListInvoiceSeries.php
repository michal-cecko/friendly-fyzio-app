<?php

namespace App\Filament\Clusters\Finance\Resources\InvoiceSeries\Pages;

use App\Filament\Clusters\Finance\Resources\InvoiceSeries\InvoiceSeriesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceSeries extends ListRecords
{
    protected static string $resource = InvoiceSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
