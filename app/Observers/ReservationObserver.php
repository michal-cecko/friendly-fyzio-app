<?php

namespace App\Observers;

use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Support\Mentions\StaffMentions;

/**
 * Sends an in-app notification to staff members newly @-mentioned in a
 * reservation's internal note. An observer (rather than form hooks) covers all
 * write paths: the resource pages, the table edit modal, the calendar widget,
 * and the public wizard (whose plain-text customer note contains no mention
 * markup, so nothing fires there).
 */
class ReservationObserver
{
    public function created(Reservation $reservation): void
    {
        $this->notifyMentions($reservation, old: null);
    }

    public function updated(Reservation $reservation): void
    {
        if ($reservation->wasChanged('notes')) {
            $this->notifyMentions($reservation, old: $reservation->getOriginal('notes'));
        }
    }

    private function notifyMentions(Reservation $reservation, ?string $old): void
    {
        if (! StaffMentions::containsMentions($reservation->notes)) {
            return;
        }

        $actor = auth()->user();

        StaffMentions::notifyNewMentions(
            old: $old,
            new: $reservation->notes,
            author: $actor,
            title: ($actor?->name ?? 'Někdo').' vás zmínil/a v poznámce k rezervaci klienta '
                .$reservation->client?->name
                .' ('.$reservation->reservation_date?->format('d.m.Y').')',
            url: ReservationResource::getUrl('view', ['record' => $reservation]),
        );
    }
}
