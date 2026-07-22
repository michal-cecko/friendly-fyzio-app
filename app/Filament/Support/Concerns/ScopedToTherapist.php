<?php

namespace App\Filament\Support\Concerns;

use App\Models\User;

/**
 * Row-level scoping for the therapist portal. A pure therapist (role Therapist)
 * only sees their own data in the shared admin resources; administrators — including
 * an admin who also practises (Therapist + Admin) keeps full, unscoped access
 * (the scope helpers return null for them, leaving the query unfiltered).
 */
trait ScopedToTherapist
{
    /**
     * The current user's staff_profiles id when they are a pure therapist,
     * else null. Used to scope reservations (`therapist_id`).
     */
    protected static function staffProfileScopeId(): ?string
    {
        return static::pureTherapist()?->staffProfile?->getKey();
    }

    /**
     * The current user's id when they are a pure therapist, else null. Used to
     * scope offerings by their `instructor_id` (courses, lessons, workshops).
     */
    protected static function therapistUserScopeId(): ?string
    {
        return static::pureTherapist()?->getKey();
    }

    protected static function pureTherapist(): ?User
    {
        $user = auth()->user();

        // A therapist who is not also an admin: admins keep full, unscoped access.
        return $user instanceof User && $user->isTherapist() && ! $user->isAdmin() ? $user : null;
    }
}
