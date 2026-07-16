<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use App\Models\CashReceipt;
use App\Support\Pdf\ReceiptPdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Staff-only HTML preview of a příjmový pokladní doklad.
 */
class ReceiptPreviewController extends Controller
{
    public function __invoke(Request $request, CashReceipt $cashReceipt, ReceiptPdfRenderer $renderer): Response
    {
        abort_unless($request->user()?->isStaff() ?? false, 403);

        return response($renderer->html($cashReceipt, forBrowserPrint: true));
    }
}
