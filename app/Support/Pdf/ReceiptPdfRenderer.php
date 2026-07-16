<?php

namespace App\Support\Pdf;

use App\Models\CashReceipt;
use Illuminate\Support\Facades\View;

/**
 * Renders a příjmový pokladní doklad to HTML or PDF. The PPD is a half-page
 * document — A5 landscape per the approved design.
 */
final class ReceiptPdfRenderer
{
    /**
     * A5 landscape, inches.
     *
     * @var array<string, float>
     */
    private const PAPER_OPTIONS = [
        'paperWidth' => 8.27,
        'paperHeight' => 5.83,
        'marginTop' => 0.25,
        'marginBottom' => 0.45,
        'marginLeft' => 0.3,
        'marginRight' => 0.3,
    ];

    public function __construct(private readonly GotenbergClient $gotenberg) {}

    /**
     * @param  bool  $forBrowserPrint  adds the browser-print @page rules (used by the
     *                                 /nahledy preview so Ctrl+P prints without the
     *                                 browser's own header/footer); never set for Gotenberg
     */
    public function html(CashReceipt $receipt, bool $forBrowserPrint = false): string
    {
        return View::make('pdf.receipt', [
            'data' => ReceiptPdfData::fromReceipt($receipt),
            'browserPrint' => $forBrowserPrint,
        ])->render();
    }

    public function footerHtml(CashReceipt $receipt): string
    {
        return View::make('pdf.footer', [
            'info' => ReceiptPdfData::footerInfoFor($receipt),
            'sidePadding' => '0.3in',
        ])->render();
    }

    public function render(CashReceipt $receipt): string
    {
        return $this->gotenberg->pdfFromHtml(
            $this->html($receipt),
            $this->footerHtml($receipt),
            PdfFonts::assets(),
            self::PAPER_OPTIONS,
        );
    }
}
