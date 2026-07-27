<?php

namespace App\Http\Controllers;

use App\Enums\ReservationDocumentType;
use App\Models\Reservation;
use App\Models\ReservationDocument;
use App\Support\Reservations\ClientReservationActions;
use App\Support\Reservations\ReservationDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The passwordless page where a client delivers the doctor's note backing a late
 * cancellation, reached from the „Doručte prosím potvrzení od lékaře" e-mail.
 *
 * It needs its own signed link ({@see Reservation::doctorNoteUploadUrl()}) because
 * {@see Reservation::manageUrl()} expires when the visit starts — always before a
 * note from the doctor can realistically arrive.
 *
 * GET only renders; each upload/removal is a POST followed by a redirect back to
 * the signed URL (PRG), so a refresh never re-submits.
 */
class DoctorNoteUploadController extends Controller
{
    public function show(Reservation $reservation): View
    {
        return view('reservations.doctor-note', [
            'reservation' => $reservation->loadMissing('service', 'therapist.user', 'client'),
        ]);
    }

    public function submit(
        Request $request,
        Reservation $reservation,
        ReservationDocuments $documents,
        ClientReservationActions $actions,
    ): RedirectResponse {
        $back = redirect()->to($reservation->doctorNoteUploadUrl());

        // Changing the resolution stays open a little wider than uploading does —
        // a client whose fee was already raised may still switch back to a note.
        if (in_array($request->input('action'), ['pay', 'deactivate'], true)) {
            return $this->changeResolution($request, $reservation, $actions, $back);
        }

        if (! $documents->canUpload($reservation)) {
            return $back;
        }

        return match ($request->input('action')) {
            'upload' => $this->upload($request, $reservation, $documents, $back),
            'delete' => $this->delete($request, $reservation, $documents, $back),
            default => $back,
        };
    }

    /**
     * The client cannot get the promised note after all: pay the fee instead, or
     * refuse and have the account deactivated (one-way — it blacklists the account).
     */
    private function changeResolution(Request $request, Reservation $reservation, ClientReservationActions $actions, RedirectResponse $back): RedirectResponse
    {
        if (! $reservation->canChangeStornoResolution()) {
            return $back;
        }

        if ($request->input('action') === 'deactivate') {
            $actions->switchToDeactivation($reservation);

            return $back;
        }

        $actions->switchToStornoPayment($reservation);

        return $back->with('doctor_note_status', 'Storno poplatek jsme vystavili — platební údaje včetně QR kódu jsme vám poslali e-mailem.');
    }

    private function upload(Request $request, Reservation $reservation, ReservationDocuments $documents, RedirectResponse $back): RedirectResponse
    {
        $validated = $request->validate([
            'documents' => ['required', 'array', 'max:5'],
            'documents.*' => $documents->rules(),
        ]);

        foreach ($validated['documents'] as $file) {
            $documents->store($reservation, $file, ReservationDocumentType::DoctorNote);
        }

        return $back->with('doctor_note_status', 'Děkujeme, potvrzení jsme přijali. Ozveme se vám, jakmile jej zkontrolujeme.');
    }

    private function delete(Request $request, Reservation $reservation, ReservationDocuments $documents, RedirectResponse $back): RedirectResponse
    {
        $document = $reservation->documents()
            ->whereKey($request->input('document'))
            ->first();

        if ($document instanceof ReservationDocument) {
            $documents->delete($document);
        }

        return $back->with('doctor_note_status', 'Soubor byl odebrán.');
    }
}
