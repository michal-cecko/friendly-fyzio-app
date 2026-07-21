<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\EventCategory;
use App\Models\OneOffEvent;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;

/**
 * Canonical registry of internal link destinations for the CMS link picker.
 *
 * A destination is stored as a single reference string:
 * "page:{uuid}" | "route:{name}" | "category:{uuid}" | "service:{uuid}" |
 * "course-category:{uuid}" | "course:{uuid}" | "event:{uuid}" |
 * "event-category:{uuid}". The legacy "lesson:"/"workshop:" kinds still resolve
 * (their ids were preserved when both merged into one-off events). Both the
 * picker options and {@see LinkResolver} read from here, so labels and
 * resolution never drift.
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
     * Grouped options for a Filament Select, keyed by reference string.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        $pages = Page::query()
            ->orderBy('title')
            ->pluck('title', 'id')
            ->mapWithKeys(fn (string $title, string $id): array => ["page:{$id}" => $title])
            ->all();

        $routes = [];
        foreach (self::ROUTES as $name => $label) {
            $routes["route:{$name}"] = $label;
        }

        $categories = ServiceCategory::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, string $id): array => ["category:{$id}" => $name])
            ->all();

        // Only services with a custom page are real standalone pages worth linking;
        // bookable services otherwise only have a minimal default page.
        $services = Service::query()
            ->has('customPage')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, string $id): array => ["service:{$id}" => $name])
            ->all();

        // Course categories link to the category-scoped course archive
        // (/kurzy?kategorie={slug}); courses and one-off events to their
        // public detail pages, event categories to their landing pages.
        $courseCategories = CourseCategory::query()
            ->published()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, string $id): array => ["course-category:{$id}" => $name])
            ->all();

        $courses = Course::query()
            ->published()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, string $id): array => ["course:{$id}" => $name])
            ->all();

        $eventCategories = EventCategory::query()
            ->published()
            ->orderBy('display_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, string $id): array => ["event-category:{$id}" => $name])
            ->all();

        $events = OneOffEvent::query()
            ->published()
            ->upcoming()
            ->orderBy('event_date')
            ->get()
            ->mapWithKeys(fn (OneOffEvent $event): array => [
                "event:{$event->getKey()}" => $event->name.' – '.$event->startsAt()->format('j. n. Y'),
            ])
            ->all();

        return array_filter([
            'Stránky' => $pages,
            'Rezervace a přihlášení' => $routes,
            'Služby' => $categories,
            'Stránky služeb' => $services,
            'Kurzy – kategorie' => $courseCategories,
            'Kurzy – detail' => $courses,
            'Jednorázové akce – kategorie' => $eventCategories,
            'Jednorázové akce' => $events,
        ]);
    }

    /**
     * Resolve a reference string to a public URL, or null if it can't be resolved.
     */
    public static function resolve(?string $ref): ?string
    {
        if ($ref === null || $ref === '') {
            return null;
        }

        [$kind, $id] = array_pad(explode(':', $ref, 2), 2, null);

        return match ($kind) {
            'page' => Page::find($id)?->permalink,
            'category' => ServiceCategory::find($id)?->permalink,
            'service' => Service::find($id)?->permalink,
            'course-category' => self::courseCategoryArchive($id),
            'course' => Course::find($id)?->permalink(),
            'event-category' => EventCategory::find($id)?->permalink,
            // Legacy aliases: lesson/workshop ids were preserved by the
            // one-off event migration, so old stored refs keep resolving.
            'event', 'lesson', 'workshop' => OneOffEvent::query()->with('category')->find($id)?->permalink(),
            'route' => $id !== null && array_key_exists($id, self::ROUTES) ? route($id) : null,
            default => null,
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
