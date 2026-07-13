<?php

namespace App\Support\Reservations;

use App\Models\Reservation;

/**
 * Captures the pre-edit state of a reservation so the "reservation changed" e-mail
 * ({{ puvodni_* }} tokens) can show the original service/therapist/termin next to the
 * new values. Snapshot before the edit is persisted, then pass the result as the
 * extra tokens of a ReservationChanged ReservationTemplateNotification.
 */
class ReservationChangeSnapshot
{
    /**
     * Read a fresh copy from the database to guarantee we capture the stored state,
     * regardless of any changes already applied to the in-memory model.
     *
     * @return array<string, string>
     */
    public static function capture(Reservation $reservation): array
    {
        $original = Reservation::query()
            ->with(['service', 'therapist.user'])
            ->find($reservation->getKey());

        if ($original === null) {
            return [];
        }

        return [
            'puvodni_sluzba' => (string) ($original->service?->name ?? ''),
            'puvodni_terapeut' => (string) ($original->therapist?->user?->name ?? ''),
            'puvodni_termin' => ReservationEmailContext::formatWhen($original),
        ];
    }
}
