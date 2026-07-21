<?php

namespace App\Support\Reservations;

use App\Models\Reservation;
use Illuminate\Support\HtmlString;

/**
 * Renders a short, human-readable summary of a reservation (customer, termín, therapist)
 * used as the description of destructive confirmation modals (delete / restore / force
 * delete) so the admin can visually confirm which reservation they are acting on.
 */
class ReservationSummary
{
    public static function description(Reservation $reservation): HtmlString
    {
        $reservation->loadMissing('client', 'therapist.user');

        $rows = [
            'Zákazník' => $reservation->client?->name,
            'Termín' => $reservation->startsAt()->format('j. n. Y, H:i'),
            'Terapeut' => $reservation->therapist?->user?->full_name,
        ];

        $html = collect($rows)
            ->map(fn (?string $value, string $label): string => '<strong>'.e($label).':</strong> '.e($value ?? '—'))
            ->implode('<br>');

        return new HtmlString($html);
    }
}
