<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a built invoice-export ZIP from the private disk (works for both the
 * local driver and S3, unlike temporaryUrl). Paths are whitelisted to the
 * invoice-exports/ folder; staff only.
 */
class InvoiceExportDownloadController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->isStaff() ?? false, 403);

        $path = base64_decode((string) $request->query('path'), true);

        abort_unless(
            is_string($path)
            && str_starts_with($path, 'invoice-exports/')
            && ! str_contains($path, '..'),
            404,
        );

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path));
    }
}
