<?php

namespace App\Filament\Clusters\Finance\Resources\CashReceipts\Actions;

use App\Models\CashReceipt;
use App\Support\Pdf\ReceiptPdfRenderer;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadReceiptPdfAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'downloadReceiptPdf';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Stáhnout PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn (CashReceipt $record): StreamedResponse => response()->streamDownload(
                fn () => print (app(ReceiptPdfRenderer::class)->render($record)),
                "{$record->receipt_number}.pdf",
                ['Content-Type' => 'application/pdf'],
            ));
    }
}
