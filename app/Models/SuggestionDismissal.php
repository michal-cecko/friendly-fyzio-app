<?php

namespace App\Models;

use Database\Factories\SuggestionDismissalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Návrhy card somebody chose to put away.
 *
 * Dismissals are team-wide, not per user: these are shared work items, so one
 * admin deciding "not now" quiets the card for everyone. `dismissed_by` records
 * who, for the trail.
 *
 * A card is hidden while a row exists with the same key AND the same
 * fingerprint AND a snooze that has not run out:
 *
 *   per-record cards carry the actionable facts in the fingerprint and no
 *   snooze, so the card comes back by itself once the situation changes;
 *   aggregate cards carry an empty fingerprint and a week's snooze, so a
 *   drifting count does not make them reappear the next day.
 *
 * @property string $key
 * @property string $type
 * @property string $fingerprint
 */
class SuggestionDismissal extends Model
{
    /** @use HasFactory<SuggestionDismissalFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'key',
        'type',
        'fingerprint',
        'snoozed_until',
        'dismissed_by',
    ];

    protected function casts(): array
    {
        return [
            'snoozed_until' => 'datetime',
        ];
    }

    public function dismissedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    /**
     * Dismissals still in force — an expired snooze hides nothing.
     *
     * @param  Builder<SuggestionDismissal>  $query
     * @return Builder<SuggestionDismissal>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $active) => $active
            ->whereNull('snoozed_until')
            ->orWhere('snoozed_until', '>', now()));
    }

    /**
     * Drops rows whose snooze has run out. From that moment they hide nothing,
     * so keeping them would only make the "Skryté návrhy" list lie.
     */
    public static function prune(): int
    {
        return static::query()
            ->whereNotNull('snoozed_until')
            ->where('snoozed_until', '<=', now())
            ->delete();
    }
}
