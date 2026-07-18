<?php

namespace App\Support\Seo;

use App\Models\CourseCategory;
use App\Models\Service;
use App\Models\ServiceCategory;

/**
 * Maps the old live-site URLs (the SEO baseline captured in
 * docs/website-content/*) onto the app's new URL scheme, so external links and
 * search-engine equity survive the move. Resolution is data-driven first (a
 * category/service is found by its own slug, so the map stays correct when
 * slugs change) with a curated array for the handful of paths that no model
 * slug can produce.
 */
class LegacyRedirects
{
    /**
     * Old paths whose new target cannot be derived from a model slug. Keys and
     * values are slash-trimmed; values may carry a query string.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'nas-tym' => '/o-nas',
        'fyzio-kurzy' => '/kurzy',
        'relaxace-ritualy/masaze' => '/sluzby/relaxace/lymphaticke-masaze',
        'relaxace-ritualy/bylinna-naparka' => '/sluzby/relaxace/bylinna-naparka',
        'prihlaska-na-jednorazove-vstupy' => '/kurzy?typ=lekce',
        'rezervace-vstupniho-vysetreni' => '/rezervace',
        'rezervace-masazi' => '/rezervace?kategorie=relaxace',
    ];

    /**
     * Resolve an old live-site path to its new canonical URL, or null when the
     * path is not a known legacy URL (the caller should then 404).
     */
    public static function resolve(string $path): ?string
    {
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        if (isset(self::MAP[$path])) {
            return self::MAP[$path];
        }

        // Old /fyzio-kurzy/{category} course-category pages → the archive filtered
        // by that category (or the unfiltered archive if it no longer exists).
        if (str_starts_with($path, 'fyzio-kurzy/')) {
            $slug = substr($path, strlen('fyzio-kurzy/'));

            return CourseCategory::where('slug', $slug)->exists()
                ? '/kurzy?kategorie='.$slug
                : '/kurzy';
        }

        // Single-segment top-level slugs that moved under /sluzby: a service
        // category (→ /sluzby/{category}) or an individual service that used to
        // live at the top level (→ /sluzby/{category}/{service}).
        if (! str_contains($path, '/')) {
            $category = ServiceCategory::published()->where('slug', $path)->first();

            if ($category !== null) {
                return $category->permalink;
            }

            $service = Service::public()->where('slug', $path)->first();

            if ($service !== null) {
                return $service->permalink;
            }
        }

        return null;
    }
}
