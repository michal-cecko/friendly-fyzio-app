<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Pages;

use App\Filament\Clusters\Finance\Resources\CashReceipts\Actions\GenerateCashReceiptAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\DownloadInvoicePdfAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\MarkInvoicePaidAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\SendInvoiceAction;
use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;

class EditInvoice extends BaseEditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadInvoicePdfAction::make(),
            Action::make('preview')
                ->label('Náhled')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (Invoice $record): string => route('invoices.preview', $record))
                ->openUrlInNewTab(),
            SendInvoiceAction::make(),
            MarkInvoicePaidAction::make(),
            GenerateCashReceiptAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
