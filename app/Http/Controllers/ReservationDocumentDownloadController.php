<?php

namespace App\Http\Controllers;

use App\Models\ReservationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a reservation attachment (today: the client's doctor's note) from the
 * private disk. Readable by staff and by the client the reservation belongs to;
 * anyone else's document is indistinguishable from a missing one (404).
 *
 * Deliberately outside the `zone.` route group — staff are bounced out of the
 * client zone by EnsureZoneCustomer, and they need this link from the admin panel.
 */
class ReservationDocumentDownloadController extends Controller
{
    public function __invoke(Request $request, ReservationDocument $document): StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $user?->isStaff() || $document->reservation?->client_id === $user?->getKey(),
            404,
        );

        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->path), 404);

        return $disk->download($document->path, $document->original_name);
    }
}
