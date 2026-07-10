<?php

namespace App\Support\Reservations;

use App\Models\Reservation;
use App\Support\Settings;
use Illuminate\Support\Str;

/**
 * Maps a reservation onto the {{ tokens }} used by the CMS reservation e-mail
 * templates. Reused by every reservation-triggered template so the tokens stay
 * consistent across pending/confirmed/reminder/… e-mails.
 */
class ReservationEmailContext
{
    /**
     * @return array<string, string>
     */
    public static function for(Reservation $reservation): array
    {
        $reservation->loadMissing('service', 'therapist.user', 'client');

        $name = (string) ($reservation->client?->name ?? '');

        return [
            'jmeno' => Str::of($name)->before(' ')->toString() ?: $name,
            'sluzba' => (string) ($reservation->service?->name ?? ''),
            'terapeut' => (string) ($reservation->therapist?->user?->name ?? ''),
            'termin' => self::formatWhen($reservation),
            'misto' => (string) (Settings::get('web.address') ?? ''),
            'odkaz' => $reservation->manageUrl(),
            'duvod' => (string) ($reservation->cancellation_reason ?? ''),
        ];
    }

    private static function formatWhen(Reservation $reservation): string
    {
        return $reservation->startsAt()->translatedFormat('j. F Y, H:i');
    }
}
