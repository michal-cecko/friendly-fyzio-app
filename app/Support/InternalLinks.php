<?php

namespace App\Support;

use App\Http\Controllers\SitemapController;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\EventCategory;
use App\Models\Lesson;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Canonical registry of internal link destinations for the CMS link picker.
 *
 * A destination is stored as a single reference string, "{kind}:{id}" — see
 * {@see InternalLinks::KINDS} for the kinds and their group labels. The legacy
 * "lesson:"/"workshop:" kinds still resolve (their ids were preserved when both
 * merged into one-off events). Both the picker options and {@see LinkResolver}
 * read from here, so labels and resolution never drift.
 *
 * Every kind lists only records that are actually reachable by the public, which
 * mirrors what {@see SitemapController} treats as a public URL.
 */
class InternalLinks
{
    /**
     * Parameterless named routes that have no backing Page/ServiceCategory row.
     *
     * @var array<string, string>
     */
    private const ROUTES = [
        'reservation.wizard' => 'Rezervace',
        'public.login' => 'Přihlášení',
    ];

    /**
     * Every destination kind, mapped to the label its group carries in the picker.
     *
     * @var array<string, string>
     */
    private const KINDS = [
        'page' => 'Stránky',
        'route' => 'Rezervace a přihlášení',
        'category' => 'Služby – kategorie',
        'service' => 'Služby – detail',
        'therapist' => 'Tým a terapeuti',
        'course-category' => 'Kurzy – kategorie',
        'course' => 'Kurzy – detail',
        'event-category' => 'Lekce – kategorie',
        'event' => 'Lekce – detail',
    ];

    /**
     * Kinds that predate the one-off event merge; their ids were preserved.
     *
     * @var array<string, string>
     */
    private const LEGACY_KINDS = [
        'lesson' => 'event',
        'workshop' => 'event',
    ];

    /**
     * The kinds a link can point at, keyed by kind.
     *
     * @return array<string, string>
     */
    public static function kinds(): array
    {
        return self::KINDS;
    }

    /**
     * Grouped options for a Filament Select, keyed by reference string.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        $groups = [];

        foreach (self::KINDS as $kind => $label) {
            $groups[$label] = self::optionsFor($kind);
        }

        return array_filter($groups);
    }

    /**
     * Options for a single kind, keyed by reference string.
     *
     * @return array<string, string>
     */
    public static function optionsFor(?string $kind): array
    {
        $kind = self::normalizeKind($kind);

        if ($kind === null) {
            return [];
        }

        if ($kind === 'route') {
            $routes = [];
            foreach (self::ROUTES as $name => $label) {
                $routes["route:{$name}"] = $label;
            }

            return $routes;
        }

        return self::query($kind)
            ->get()
            ->mapWithKeys(fn (Model $record): array => [
                "{$kind}:{$record->getKey()}" => self::labelFor($kind, $record),
            ])
            ->all();
    }

    /**
     * The kind a stored reference points at, with legacy kinds folded onto their
     * current equivalent. Null when the reference is empty or unknown.
     */
    public static function kindOf(?string $ref): ?string
    {
        return self::parse($ref)[0];
    }

    /**
     * The display label for a stored reference, even when the record is no longer
     * offered in the picker (a past event, a since-unpublished page).
     */
    public static function label(?string $ref): ?string
    {
        [$kind, $id] = self::parse($ref);

        if ($kind === null || $id === null) {
            return null;
        }

        if ($kind === 'route') {
            return self::ROUTES[$id] ?? null;
        }

        $record = self::find($kind, $id);

        return $record !== null ? self::labelFor($kind, $record) : null;
    }

    /**
     * Resolve a reference string to a public URL, or null if it can't be resolved.
     */
    public static function resolve(?string $ref): ?string
    {
        [$kind, $id] = self::parse($ref);

        if ($kind === null || $id === null) {
            return null;
        }

        if ($kind === 'route') {
            return array_key_exists($id, self::ROUTES) ? route($id) : null;
        }

        // Course categories have no page of their own, only a scoped archive.
        if ($kind === 'course-category') {
            return self::courseCategoryArchive($id);
        }

        $record = self::find($kind, $id);

        if ($record === null) {
            return null;
        }

        // Course and Lesson expose their permalink as a plain method; every other
        // destination implements HasPermalink and exposes it as an attribute.
        return $record instanceof Course || $record instanceof Lesson
            ? $record->permalink()
            : $record->permalink;
    }

    /**
     * Split a reference into its kind and id, normalising legacy kinds.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function parse(?string $ref): array
    {
        if ($ref === null || $ref === '') {
            return [null, null];
        }

        [$kind, $id] = array_pad(explode(':', $ref, 2), 2, null);

        return [self::normalizeKind($kind), $id];
    }

    private static function normalizeKind(?string $kind): ?string
    {
        $kind = self::LEGACY_KINDS[$kind] ?? $kind;

        return $kind !== null && array_key_exists($kind, self::KINDS) ? $kind : null;
    }

    /**
     * The listing query for one kind, scoped to publicly reachable records.
     *
     * @return Builder<covariant Model>
     */
    private static function query(string $kind): Builder
    {
        return match ($kind) {
            // Pages attached to an owner canonicalise to that owner's URL, and the
            // owner is separately pickable, so only standalone pages are listed.
            'page' => Page::query()
                ->published()
                ->whereNull('pageable_type')
                ->orderBy('title'),
            'category' => ServiceCategory::query()
                ->published()
                ->orderBy('name'),
            // Publicly visible services plus the hidden ones that own a custom page:
            // topic/landing services are deliberately kept out of the booking wizard
            // but still render their authored page.
            'service' => Service::query()
                ->with('category')
                ->whereHas('category')
                ->where(function (Builder $query): void {
                    $query->public()->orWhereHas('customPage');
                })
                ->orderBy('name'),
            // The name lives on the related user, so order by it through a subquery.
            'therapist' => StaffProfile::query()
                ->published()
                ->with('user')
                ->orderBy(User::query()->select('name')->whereColumn('users.id', 'staff_profiles.user_id')),
            'course-category' => CourseCategory::query()
                ->published()
                ->orderBy('name'),
            'course' => Course::query()
                ->published()
                ->orderBy('name'),
            'event-category' => EventCategory::query()
                ->published()
                ->orderBy('display_order')
                ->orderBy('name'),
            'event' => Lesson::query()
                ->published()
                ->upcoming()
                ->with('category')
                ->orderBy('lesson_date'),
        };
    }

    /**
     * Find a single record of the given kind, eager-loading whatever its label and
     * permalink need.
     */
    private static function find(string $kind, string $id): ?Model
    {
        return match ($kind) {
            'page' => Page::find($id),
            'category' => ServiceCategory::find($id),
            'service' => Service::query()->with('category')->find($id),
            'therapist' => StaffProfile::query()->with('user')->find($id),
            'course-category' => CourseCategory::find($id),
            'course' => Course::find($id),
            'event-category' => EventCategory::find($id),
            'event' => Lesson::query()->with('category')->find($id),
            default => null,
        };
    }

    /**
     * How a record is named in the picker.
     */
    private static function labelFor(string $kind, Model $record): string
    {
        return match ($kind) {
            'page' => $record->title,
            // Vstupní/kontrolní pairs repeat across categories, so name the category too.
            'service' => $record->category !== null
                ? "{$record->name} ({$record->category->name})"
                : $record->name,
            'therapist' => $record->user?->full_name ?? $record->slug,
            'event' => $record->displayName().' – '.$record->startsAt()->format('j. n. Y'),
            default => $record->name,
        };
    }

    /**
     * The course archive scoped to a category (/kurzy?kategorie={slug}), matching
     * the CourseArchive Livewire component's "kategorie" query parameter.
     */
    private static function courseCategoryArchive(?string $id): ?string
    {
        $category = $id !== null ? CourseCategory::find($id) : null;

        if ($category === null) {
            return null;
        }

        return url('/kurzy').'?kategorie='.$category->slug;
    }
}
