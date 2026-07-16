<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Pdf\InvoicePdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Staff-only HTML preview of an invoice — the same markup Gotenberg converts,
 * without the PDF round-trip. Fast design iteration + the test surface.
 */
class InvoicePreviewController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, InvoicePdfRenderer $renderer): Response
    {
        abort_unless($request->user()?->isStaff() ?? false, 403);

        return response($renderer->html($invoice, forBrowserPrint: true));
    }
}
