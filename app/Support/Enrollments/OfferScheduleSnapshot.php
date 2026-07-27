<?php

namespace App\Support\Enrollments;

use App\Models\Lesson;
use App\Support\Reservations\ReservationChangeSnapshot;

/**
 * Captures a session's stored schedule before an edit is saved, so the
 * "schedule changed" e-mail can show the original termin/místo
 * ({{ puvodni_termin }} / {{ puvodni_misto }}) next to the new ones. Mirrors
 * {@see ReservationChangeSnapshot}: reads a fresh copy
 * from the database so the captured value is the stored (old) one, regardless of
 * any changes already applied to the in-memory model.
 */
class OfferScheduleSnapshot
{
    /**
     * @return array<string, string>
     */
    public static function capture(Lesson $scheduled): array
    {
        $original = $scheduled->newQuery()->with('room.building')->find($scheduled->getKey());

        if ($original === null) {
            return [];
        }

        return [
            'puvodni_termin' => EnrollmentEmailContext::dateTimeLabel($original->startsAt()),
            'puvodni_misto' => EnrollmentEmailContext::place($original->room),
        ];
    }
}
