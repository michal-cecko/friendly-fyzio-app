<?php

namespace App\Filament\Clusters\Finance\Resources\CashReceipts\Pages;

use App\Filament\Clusters\Finance\Resources\CashReceipts\Actions\DownloadReceiptPdfAction;
use App\Filament\Clusters\Finance\Resources\CashReceipts\CashReceiptResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Models\CashReceipt;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;

class EditCashReceipt extends BaseEditRecord
{
    protected static string $resource = CashReceiptResource::class;

    public function getTitle(): string
    {
        /** @var CashReceipt $record */
        $record = $this->getRecord();

        return 'Upravit pokladní doklad '.$record->receipt_number;
    }

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
