<?php

namespace App\Filament\Clusters\Finance\Resources\CashReceipts\Pages;

use App\Filament\Clusters\Finance\Resources\CashReceipts\Actions\DownloadReceiptPdfAction;
use App\Filament\Clusters\Finance\Resources\CashReceipts\CashReceiptResource;
use App\Models\CashReceipt;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCashReceipt extends ViewRecord
{
    protected static string $resource = CashReceiptResource::class;

    public function getTitle(): string
    {
        /** @var CashReceipt $record */
        $record = $this->getRecord();

        return 'Pokladní doklad '.$record->receipt_number;
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
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
