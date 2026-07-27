<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\User;

/**
 * For widgets that show the viewer their *own* work — their day, their lessons.
 *
 * Deliberately **not** {@see User::isScopedToOwnWork()}, which means "narrow the
 * panel to this person because they are not an admin". An admin who also
 * practises or teaches has a day of their own to look at, so these widgets are
 * about holding the capability, not about lacking admin rights: they sit next to
 * the clinic-wide {@see AdminOnly} widgets rather than replacing them.
 *
 * The two id helpers are the keys the viewer's own records hang off — a staff
 * profile for reservations (`reservations.therapist_id`) and a user for
 * offerings (`*.instructor_id`) — resolved fresh per call, so nothing is cached
 * that could leak between requests under Octane.
 */
trait OwnWork
{
    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isTherapist() || $user?->isLecturer());
    }

    protected function viewer(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * The viewer's own `staff_profiles` id, keyed on by their reservations.
     */
    protected function ownStaffProfileId(): ?string
    {
        return $this->viewer()?->staffProfile?->getKey();
    }

    /**
     * The viewer's own `users` id, keyed on by the offerings they instruct.
     */
    protected function ownUserId(): ?string
    {
        return $this->viewer()?->getKey();
    }
}
