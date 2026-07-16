<?php

namespace App\Support\Reservations;

use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use Illuminate\Support\Str;

/**
 * Maps a reservation onto the {{ tokens }} used by the therapist-facing CMS reservation
 * e-mail templates. Mirrors {@see ReservationEmailContext} but addresses the therapist
 * ({{ jmeno }}) and describes the client + appointment; {{ odkaz }} points at the
 * reservation in the admin.
 */
class TherapistReservationEmailContext
{
    /**
     * @param  array<string, string>  $extra  Trigger-specific tokens merged over the base set
     *                                        (e.g. storno_reseni, puvodni_termin).
     * @return array<string, string>
     */
    public static function for(Reservation $reservation, array $extra = []): array
    {
        $reservation->loadMissing('service', 'therapist.user', 'client');

        $therapistName = (string) ($reservation->therapist?->user?->name ?? '');

        return [
            'jmeno' => Str::of($therapistName)->before(' ')->toString() ?: $therapistName,
            'sluzba' => (string) ($reservation->service?->name ?? ''),
            'klient' => (string) ($reservation->client?->name ?? ''),
            'telefon_klienta' => (string) ($reservation->client?->phone ?? ''),
            'email_klienta' => (string) ($reservation->client?->email ?? ''),
            'termin' => ReservationEmailContext::formatWhen($reservation),
            'odkaz' => ReservationResource::getUrl('view', ['record' => $reservation]),
            ...$extra,
        ];
    }
}
