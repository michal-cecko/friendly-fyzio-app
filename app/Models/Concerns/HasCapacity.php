<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Spot accounting shared by the enrollable offers (course series, one-time
 * lessons, workshops). The model exposes an `activeTakers()` relation returning
 * only sign-ups that occupy a spot (cancelled and waitlisted ones never count);
 * capacity math builds on that. List queries should eager-load the count via
 * `withTakenSpots()` so cards don't trigger per-row COUNTs.
 */
trait HasCapacity
{
    /**
     * Sign-ups currently occupying a spot.
     */
    abstract public function activeTakers(): HasMany;

    public function scopeWithTakenSpots(Builder $query): Builder
    {
        return $query->withCount('activeTakers');
    }

    /**
     * Only offers with at least one free spot: capacity > COUNT(activeTakers).
     * The correlated subquery is built from the model's own relation, so each
     * model's occupancy rules (which statuses hold a spot) stay in one place.
     */
    public function scopeHasSpotsLeft(Builder $query): Builder
    {
        $model = $query->getModel();

        /** @var HasMany $relation */
        $relation = Relation::noConstraints(fn () => $model->activeTakers());

        $count = $relation
            ->getRelationExistenceCountQuery($relation->getRelated()->newQuery(), $query)
            ->mergeConstraintsFrom($relation->getQuery());

        return $query->where($model->qualifyColumn('capacity'), '>', $count);
    }

    public function takenSpots(): int
    {
        $eager = $this->getAttribute('active_takers_count');

        return $eager !== null ? (int) $eager : $this->activeTakers()->count();
    }

    public function spotsLeft(): int
    {
        return max(0, (int) $this->capacity - $this->takenSpots());
    }

    public function isFull(): bool
    {
        return $this->spotsLeft() === 0;
    }

    /**
     * Whether freed spots are offered to the waitlist automatically. When off,
     * staff promote manually from the waitlist tab instead.
     */
    public function autoPromotesWaitlist(): bool
    {
        return (bool) ($this->auto_promote_waitlist ?? true);
    }
}
