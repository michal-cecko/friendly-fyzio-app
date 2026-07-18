<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Pdf\InvoicePdfRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lets a client download their own invoice PDF from the client zone. The PDF is
 * rendered on demand (Gotenberg) exactly like the admin action — nothing is
 * persisted. Invoices belonging to anyone else are indistinguishable from
 * missing ones (404).
 */
class CustomerInvoiceDownloadController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice): StreamedResponse
    {
        abort_unless($invoice->client_id === $request->user()->getKey(), 404);

        return response()->streamDownload(
            fn () => print (app(InvoicePdfRenderer::class)->render($invoice)),
            "{$invoice->invoice_number}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
