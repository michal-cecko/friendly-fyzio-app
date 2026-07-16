<?php

namespace App\Filament\Clusters\Finance\Resources\CashReceipts\Pages;

use App\Filament\Clusters\Finance\Resources\CashReceipts\Actions\DownloadReceiptPdfAction;
use App\Filament\Clusters\Finance\Resources\CashReceipts\CashReceiptResource;
use App\Models\CashReceipt;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditCashReceipt extends EditRecord
{
    protected static string $resource = CashReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadReceiptPdfAction::make(),
            Action::make('preview')
                ->label('Náhled')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (CashReceipt $record): string => route('cash-receipts.preview', $record))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
