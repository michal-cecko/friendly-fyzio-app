<?php

namespace App\Support;

use App\Filament\Support\Concerns\ScopedToTherapist;
use App\Models\User;
use App\Support\Reservations\Conflict;

/**
 * Whose work the Návrhy (and Problémy) surfaces are about.
 *
 * A therapist or lecturer who is not also an admin sees only their own: reservations they run
 * (`reservations.therapist_id`) and offerings they teach
 * (`courses.instructor_id`, `lessons.instructor_id`) — the same two keys
 * {@see ScopedToTherapist} uses for the shared
 * admin resources, so a card can never link to a record its owner cannot open.
 *
 * Administrators — including an admin who also practises — are unscoped.
 *
 * Resolved fresh from the current user on every call; nothing is cached, so
 * there is no per-request state to leak under Octane.
 */
final class StaffScope
{
    private function __construct(
        public readonly ?string $staffProfileId,
        public readonly ?string $userId,
    ) {}

    public static function current(): self
    {
        return self::for(auth()->user());
    }

    public static function for(?object $user): self
    {
        if (! $user instanceof User || ! $user->isScopedToOwnWork()) {
            return new self(null, null);
        }

        return new self($user->staffProfile?->getKey(), $user->getKey());
    }

    /**
     * True when the cards must be narrowed to one therapist's own work.
     */
    public function isScoped(): bool
    {
        return $this->userId !== null;
    }

    /**
     * Conflicts this scope is a party to. A room double-booking between two
     * other people is their problem, not this therapist's — but a clash where
     * either side is theirs is theirs to sort out.
     *
     * @param  list<Conflict>  $conflicts
     * @return list<Conflict>
     */
    public function filterConflicts(array $conflicts): array
    {
        if (! $this->isScoped()) {
            return $conflicts;
        }

        return array_values(array_filter(
            $conflicts,
            fn (Conflict $conflict): bool => in_array(
                $this->staffProfileId,
                [$conflict->a->therapistId, $conflict->b->therapistId],
                true,
            ),
        ));
    }
}
