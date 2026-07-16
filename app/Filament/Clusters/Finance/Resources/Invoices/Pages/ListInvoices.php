<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Pages;

use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Filament\Exports\InvoiceExporter;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(InvoiceExporter::class)
                ->label('Export pro účetní'),
            CreateAction::make(),
        ];
    }
}
