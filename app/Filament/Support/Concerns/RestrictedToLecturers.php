<?php

namespace App\Filament\Support\Concerns;

use App\Models\User;

/**
 * Limits a Kurzy resource to the people who teach.
 *
 * The course catalogue, its série, lessons, rosters and attendance only concern
 * someone holding the Lecturer capability; a therapist who does not teach has no
 * business in any of it, so the whole cluster stays out of their panel — sidebar,
 * global search and the pages themselves. Administrators keep full access.
 *
 * Row-level scoping is a separate matter: a lecturer who passes this gate still
 * only sees their own offerings ({@see ScopedToTherapist}).
 */
trait RestrictedToLecturers
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isAdmin() || $user->isLecturer())
            && parent::canAccess();
    }
}
