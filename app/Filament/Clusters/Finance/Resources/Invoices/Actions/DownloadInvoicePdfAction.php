<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Actions;

use App\Models\Invoice;
use App\Support\Pdf\InvoicePdfRenderer;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadInvoicePdfAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'downloadInvoicePdf';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Stáhnout PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn (Invoice $record): StreamedResponse => response()->streamDownload(
                fn () => print (app(InvoicePdfRenderer::class)->render($record)),
                "{$record->invoice_number}.pdf",
                ['Content-Type' => 'application/pdf'],
            ));
    }
}
