<?php

namespace App\Filament\Support\Concerns;

use App\Models\User;

/**
 * Row-level scoping for the therapist portal. Staff who treat or teach (role
 * Therapist or Lecturer) only see their own data in the shared admin resources;
 * administrators — including an admin who also practises (Therapist + Admin) —
 * keep full, unscoped access (the scope helpers return null for them, leaving
 * the query unfiltered). See {@see User::isScopedToOwnWork()}.
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

        // Someone who treats or teaches but is not an admin: admins keep full,
        // unscoped access.
        return $user instanceof User && $user->isScopedToOwnWork() ? $user : null;
    }
}
