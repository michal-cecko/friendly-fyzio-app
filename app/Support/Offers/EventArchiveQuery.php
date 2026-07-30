<?php

namespace App\Support\Offers;

use App\Enums\OfferVisibility;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Builder;

/**
 * The public-visibility rules for one-off events, shared by the two archives
 * that list them: the standalone event archive (category landing pages) and
 * the "Jednorázové lekce" tab of the course archive. Keeping them in one place
 * is what stops the two archives drifting apart on private offers.
 */
class EventArchiveQuery
{
    /**
     * Publicly listable events, optionally narrowed to a set of category slugs
     * (an empty list means "every category").
     *
     * @param  array<int, string>  $categorySlugs
     */
    public static function base(array $categorySlugs = [], bool $includePrivate = false): Builder
    {
        return Lesson::query()
            ->published()
            // An event only has a public address once it has both — a scheduled
            // lesson of a série has neither until its free place is released.
            ->whereNotNull('slug')
            ->whereNotNull('event_category_id')
            ->when(! $includePrivate, fn (Builder $query) => $query->where('visibility', OfferVisibility::Public))
            ->when($categorySlugs !== [], fn (Builder $query) => $query
                ->whereHas('category', fn (Builder $category) => $category->whereIn('slug', $categorySlugs)))
            ->withOccupancyCounts()
            ->with(['room', 'category', 'course', 'series.course']);
    }

    /**
     * Case-insensitive search over the event's own name/description with a
     * fallback to the linked course's name (lesson-type events often derive
     * their content from the course).
     */
    public static function applySearch(Builder $query, string $search): Builder
    {
        $needle = '%'.mb_strtolower(trim($search)).'%';

        return $query->where(fn (Builder $inner) => $inner
            ->whereRaw('LOWER(name) LIKE ?', [$needle])
            ->orWhereRaw('LOWER(description) LIKE ?', [$needle])
            ->orWhereHas('course', fn (Builder $course) => $course
                ->whereRaw('LOWER(name) LIKE ?', [$needle])));
    }
}
