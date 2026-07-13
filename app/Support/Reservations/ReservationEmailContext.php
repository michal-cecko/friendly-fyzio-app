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
     * @param  array<string, string>  $extra  Trigger-specific tokens merged over the base set
     *                                        (e.g. the puvodni_* values for a changed reservation).
     * @return array<string, string>
     */
    public static function for(Reservation $reservation, array $extra = []): array
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
            'telefon' => (string) ($reservation->client?->phone ?? ''),
            'email' => (string) ($reservation->client?->email ?? ''),
            'pripominka_hodin' => (string) Settings::reminderHours(),
            'auto_zruseni_hodin' => (string) Settings::autoCancelHours(),
            'storno_hodin' => (string) $reservation->cancelBeforeHours(),
            'potvrzeni_hodin' => (string) Settings::confirmationHours(),
            'storno_procenta' => (string) Settings::stornoFeePercent(),
            ...$extra,
        ];
    }

    public static function formatWhen(Reservation $reservation): string
    {
        return $reservation->startsAt()->translatedFormat('j. F Y, H:i');
    }
}
