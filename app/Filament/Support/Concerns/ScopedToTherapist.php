<?php

namespace App\Filament\Support\Concerns;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Row-level scoping for the therapist portal. A pure therapist (role Therapist)
 * only sees their own data in the shared admin resources; administrators — including
 * an admin who also practises via `acts_as_therapist` — keep full access (the scope
 * helpers return null for them, leaving the query unfiltered).
 */
trait ScopedToTherapist
{
    /**
     * The current user's therapist_profiles id when they are a pure therapist,
     * else null. Used to scope reservations (`therapist_id`).
     */
    protected static function therapistProfileScopeId(): ?string
    {
        return static::pureTherapist()?->therapistProfile?->getKey();
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

        return $user instanceof User && $user->role === UserRole::Therapist ? $user : null;
    }
}
