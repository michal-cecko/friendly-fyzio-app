<?php

namespace App\Models\Concerns;

use App\Enums\PaymentStatus;
use App\Enums\WaitlistPromotionMode;
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

    /**
     * Drops offers whose free spots are currently fenced off for their waitlist,
     * so a public "jen volná místa" filter agrees with the Full badge the detail
     * page shows during an invite round.
     */
    public function scopeWithoutActiveWaitlistInvite(Builder $query): Builder
    {
        return $query->where(fn (Builder $nested) => $nested
            ->whereNull($this->qualifyColumn('waitlist_invited_until'))
            ->orWhere($this->qualifyColumn('waitlist_invited_until'), '<=', now()));
    }

    public function takenSpots(): int
    {
        $eager = $this->getAttribute('active_takers_count');

        return $eager !== null ? (int) $eager : $this->activeTakers()->count();
    }

    /**
     * Spot-occupying sign-ups that are already paid. Used to resolve an
     * over-invited "who is faster to pay" race: once this reaches capacity, the
     * remaining unpaid over-invites are closed out.
     */
    public function paidTakers(): int
    {
        return $this->activeTakers()->where('payment_status', PaymentStatus::Paid->value)->count();
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
     * What happens to a freed spot: nothing (staff promote by hand), an invite
     * round, or a straight sign-up for the next in line.
     */
    public function waitlistPromotionMode(): WaitlistPromotionMode
    {
        return $this->waitlist_promotion_mode ?? WaitlistPromotionMode::AutomaticAdd;
    }

    /**
     * Whether an invite round is still running, i.e. the freed spot is reserved
     * for the waitlist and the public sign-up form must keep showing "full".
     * Invited waiters get past it through the offer's hidden link.
     */
    public function waitlistInviteActive(): bool
    {
        return $this->waitlist_invited_until !== null
            && $this->waitlist_invited_until->isFuture();
    }
}
