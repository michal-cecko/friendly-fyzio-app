<?php

namespace App\Support\Enrollments;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Models\Course;
use App\Models\CourseCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;

/**
 * Course categories that are currently enrolling, for the homepage
 * "Právě přihlašujeme" brick. A category appears when at least one of its
 * published courses has a publicly listed, currently-open run (status Open, not
 * yet ended); each course row deep-links to its detail page and the category
 * header to the category-scoped archive (/kurzy?kategorie={slug}). The homepage
 * renders this on every visit, so the computed result is cached for a few
 * minutes; the course archive remains the source of truth on click.
 *
 * @phpstan-type CourseRow array{label: string, meta: string, url: string}
 * @phpstan-type CategoryCard array{title: string, subtitle: string, icon: string, url: string, items: array<int, CourseRow>}
 */
class EnrollingNow
{
    private const CACHE_KEY = 'brick.enrolling-now.categories';

    private const CACHE_TTL_SECONDS = 300;

    private const MAX_COURSES_PER_CATEGORY = 5;

    /**
     * Lucide icon per known category slug; unknown slugs fall back to a neutral
     * default (course categories carry no icon of their own).
     *
     * @var array<string, string>
     */
    private const ICONS = [
        'joga' => 'flower-2',
        'skupinova-cviceni' => 'users',
        'pohybove-kurzy' => 'activity',
        'kurzy-pro-rodice-s-detmi' => 'baby',
    ];

    /**
     * @return array<int, CategoryCard>
     */
    public static function cached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => self::compute());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, CategoryCard>
     */
    public static function compute(): array
    {
        return CourseCategory::query()
            ->published()
            ->orderBy('display_order')
            ->orderBy('name')
            ->with(['courses' => fn ($courses) => $courses
                ->published()
                ->whereHas('series', fn (Builder $series): Builder => self::openSeries($series))
                ->with(['series' => fn ($series) => self::openSeries($series)
                    ->orderBy('start_date')
                    ->orderBy('id')])
                ->orderBy('name')])
            ->get()
            ->map(fn (CourseCategory $category): ?array => self::card($category))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * A currently-open run: publicly listed, actively enrolling and not yet ended.
     * Accepts either a query Builder (from whereHas) or a relation (from an
     * eager-load closure), returning the same to keep the chain fluent.
     */
    private static function openSeries(Builder|Relation $series): Builder|Relation
    {
        return $series
            ->where('visibility', CourseSeriesVisibility::Public)
            ->where('status', CourseSeriesStatus::Open)
            ->whereDate('end_date', '>=', today());
    }

    /**
     * @return CategoryCard|null Null when the category has no open course.
     */
    private static function card(CourseCategory $category): ?array
    {
        $courses = $category->courses;

        if ($courses->isEmpty()) {
            return null;
        }

        return [
            'title' => $category->name,
            'subtitle' => self::countLabel($courses->count()),
            'icon' => self::ICONS[$category->slug] ?? 'graduation-cap',
            'url' => url('/kurzy').'?kategorie='.$category->slug,
            'items' => $courses
                ->take(self::MAX_COURSES_PER_CATEGORY)
                ->map(fn (Course $course): array => [
                    'label' => $course->name,
                    'meta' => self::startLabel($course),
                    'url' => $course->permalink(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * "Začíná {date}" for the soonest upcoming run, or "Probíhá" once it has
     * already started (an open run stays joinable until it ends).
     */
    private static function startLabel(Course $course): string
    {
        $soonest = $course->series->first();

        if ($soonest === null) {
            return '';
        }

        return $soonest->start_date->greaterThanOrEqualTo(today())
            ? 'Začíná '.$soonest->start_date->format('j. n. Y')
            : 'Probíhá';
    }

    /**
     * Czech-pluralised open-course count, e.g. "1 otevřený kurz",
     * "3 otevřené kurzy", "5 otevřených kurzů".
     */
    private static function countLabel(int $count): string
    {
        $noun = match (true) {
            $count === 1 => 'otevřený kurz',
            $count >= 2 && $count <= 4 => 'otevřené kurzy',
            default => 'otevřených kurzů',
        };

        return $count.' '.$noun;
    }
}
