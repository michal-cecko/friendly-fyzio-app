<?php

namespace App\Observers;

use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\ClientNote;
use App\Support\Mentions\StaffMentions;

/**
 * Sends an in-app notification to staff members newly @-mentioned in a therapy
 * note, no matter which surface saved it (client profile or reservation view).
 */
class ClientNoteObserver
{
    public function created(ClientNote $note): void
    {
        $this->notifyMentions($note, old: null);
    }

    public function updated(ClientNote $note): void
    {
        if ($note->wasChanged('content')) {
            $this->notifyMentions($note, old: $note->getOriginal('content'));
        }
    }

    private function notifyMentions(ClientNote $note, ?string $old): void
    {
        if (! StaffMentions::containsMentions($note->content)) {
            return;
        }

        $actor = auth()->user();

        StaffMentions::notifyNewMentions(
            old: $old,
            new: $note->content,
            author: $actor,
            title: ($actor?->name ?? 'Někdo').' vás zmínil/a v poznámce u klienta '.$note->client?->name,
            url: $note->reservation_id !== null
                ? ReservationResource::getUrl('view', ['record' => $note->reservation_id])
                : ClientResource::getUrl('view', ['record' => $note->client_id]),
        );
    }
}
