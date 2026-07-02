<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;

/**
 * Canonical registry of internal link destinations for the CMS link picker.
 *
 * A destination is stored as a single reference string:
 * "page:{uuid}" | "route:{name}" | "category:{uuid}" | "service:{uuid}". Both the
 * picker options and {@see LinkResolver} read from here, so labels and resolution
 * never drift.
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

        return array_filter([
            'Stránky' => $pages,
            'Rezervace a přihlášení' => $routes,
            'Služby' => $categories,
            'Stránky služeb' => $services,
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
            'route' => $id !== null && array_key_exists($id, self::ROUTES) ? route($id) : null,
            default => null,
        };
    }
}
