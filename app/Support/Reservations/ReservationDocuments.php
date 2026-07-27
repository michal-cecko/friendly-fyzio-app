<?php

namespace App\Support\Reservations;

use App\Enums\Capability;
use App\Enums\ReservationDocumentType;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Models\ReservationDocument;
use App\Models\User;
use App\Support\ActivityLog\LogActivity;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The single writer for reservation attachments, shared by the three surfaces that
 * accept a doctor's note: the authenticated client zone, the passwordless signed
 * upload link, and (for completeness) the admin panel. Mirrors how
 * {@see ClientReservationActions} centralises the customer's cancel decisions.
 *
 * Files go to the private disk. Never `public`, and never the Filament media
 * library — a medical document must not be browsable from the media picker.
 */
class ReservationDocuments
{
    public const DISK = 'local';

    public const DIRECTORY = 'reservation-documents';

    /**
     * Validation rules for one uploaded file of the given type.
     *
     * @return array<int, string>
     */
    public function rules(ReservationDocumentType $type = ReservationDocumentType::DoctorNote): array
    {
        return $type->rules();
    }

    /**
     * Whether the client may still attach (or remove) a doctor's note: one was
     * promised and staff have not resolved it yet. Once resolved the fee was either
     * waived or charged, and the evidence must stay as it was.
     */
    public function canUpload(Reservation $reservation): bool
    {
        return $reservation->awaitsDoctorNote();
    }

    /**
     * Store one uploaded file against the reservation. For a doctor's note this also
     * alerts staff — the delivery is what unblocks their „Vyřešit storno" decision.
     */
    public function store(
        Reservation $reservation,
        UploadedFile $file,
        ReservationDocumentType $type = ReservationDocumentType::DoctorNote,
        ?User $uploader = null,
    ): ReservationDocument {
        $path = $file->store(self::DIRECTORY.'/'.$reservation->getKey(), self::DISK);

        /** @var ReservationDocument $document */
        $document = $reservation->documents()->create([
            'type' => $type,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => $uploader?->getKey(),
        ]);

        if ($type === ReservationDocumentType::DoctorNote) {
            $this->notifyStaffOfUpload($reservation, $document);

            LogActivity::record('reservation_doctor_note_uploaded', $reservation, 'Potvrzení od lékaře nahráno', [
                'source' => $uploader !== null ? 'Klient (klientská zóna)' : 'Klient (odkaz z e-mailu)',
                'file' => $document->original_name,
            ], $uploader ?? $reservation->client);
        }

        return $document;
    }

    /**
     * Remove a document the client uploaded by mistake. Refused once staff resolved
     * the note — at that point the file is the record behind their decision.
     */
    public function delete(ReservationDocument $document): bool
    {
        if ($document->type === ReservationDocumentType::DoctorNote
            && ! $this->canUpload($document->reservation)) {
            return false;
        }

        return (bool) $document->delete();
    }

    /**
     * Wipe every stored file for a reservation (used when the whole record goes).
     * The model's `deleted` hook removes the bytes; this drops the folder too.
     */
    public function purge(Reservation $reservation): void
    {
        $reservation->documents->each->delete();

        Storage::disk(self::DISK)->deleteDirectory(self::DIRECTORY.'/'.$reservation->getKey());
    }

    /**
     * Tell the admins a promised note has actually arrived, so it can be reviewed
     * through the existing „Vyřešit storno (lékařské potvrzení)" action.
     */
    protected function notifyStaffOfUpload(Reservation $reservation, ReservationDocument $document): void
    {
        $admins = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', Capability::Admin->roleName()))
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Potvrzení od lékaře nahráno')
            ->body(($reservation->client?->name ?? 'Klient').' nahrál potvrzení od lékaře ('.$document->original_name.'). Zkontrolujte jej a vyřešte storno.')
            ->icon('heroicon-o-paper-clip')
            ->info()
            ->actions([
                Action::make('open')
                    ->label('Zobrazit rezervaci')
                    ->url(ReservationResource::getUrl('view', ['record' => $reservation])),
            ])
            ->sendToDatabase($admins);
    }
}
