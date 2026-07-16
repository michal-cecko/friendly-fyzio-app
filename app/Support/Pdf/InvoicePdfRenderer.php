<?php

namespace App\Support\Pdf;

use App\Models\Invoice;
use Illuminate\Support\Facades\View;

/**
 * Renders an invoice to HTML (fast preview / tests) or to PDF bytes via Gotenberg.
 * PDFs are never persisted — always re-rendered from the stored snapshot state.
 */
final class InvoicePdfRenderer
{
    public function __construct(private readonly GotenbergClient $gotenberg) {}

    /**
     * @param  bool  $forBrowserPrint  adds the browser-print @page rules (used by the
     *                                 /nahledy preview so Ctrl+P prints without the
     *                                 browser's own header/footer); never set for Gotenberg
     */
    public function html(Invoice $invoice, bool $forBrowserPrint = false): string
    {
        return View::make('pdf.invoice', [
            'data' => InvoicePdfData::fromInvoice($invoice),
            'browserPrint' => $forBrowserPrint,
        ])->render();
    }

    public function footerHtml(Invoice $invoice): string
    {
        return View::make('pdf.footer', [
            'info' => InvoicePdfData::footerInfoFor($invoice),
            'sidePadding' => '0.4in',
        ])->render();
    }

    public function render(Invoice $invoice): string
    {
        return $this->gotenberg->pdfFromHtml($this->html($invoice), $this->footerHtml($invoice), PdfFonts::assets());
    }
}
