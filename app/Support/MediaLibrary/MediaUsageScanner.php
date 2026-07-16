<?php

namespace App\Support\MediaLibrary;

use App\Models\Banner;
use App\Models\EmailTemplate;
use App\Models\InstagramPost;
use App\Models\Page;
use App\Models\ServiceCategory;
use App\Models\TherapistProfile;

/**
 * Finds every place a media library item is referenced so it cannot be deleted
 * while still in use. Item ids are integers, so matching is strictly typed:
 * scalar image columns compare by value, JSON brick content is walked for the
 * known image keys, and WYSIWYG HTML inside the content is searched for the
 * media plugin's `data-id="{id}"` / `data-id="{id}:{conversion}"` tokens.
 */
class MediaUsageScanner
{
    /**
     * Config keys under which MediaPicker fields store an item id (bricks,
     * banners) — see the MediaPicker::make() calls across the app.
     */
    private const IMAGE_KEYS = ['image', 'avatar', 'photo', 'featured_image', 'hero_image'];

    /**
     * Human-readable descriptions of every place the item id is referenced.
     *
     * @return array<int, string>
     */
    public static function usagesOf(int $itemId): array
    {
        $usages = [];

        foreach (Page::withTrashed()->get(['id', 'title', 'content', 'featured_image']) as $page) {
            if (self::matchesId($page->featured_image, $itemId) || self::contentReferences($page->content, $itemId)) {
                $usages[] = 'Stránka „'.$page->title.'“';
            }
        }

        foreach (Banner::query()->get(['id', 'name', 'content']) as $banner) {
            if (self::contentReferences($banner->content, $itemId)) {
                $usages[] = 'Banner „'.$banner->name.'“';
            }
        }

        foreach (EmailTemplate::query()->get(['id', 'name', 'content']) as $template) {
            if (self::contentReferences($template->content, $itemId)) {
                $usages[] = 'E-mailová šablona „'.$template->name.'“';
            }
        }

        foreach (ServiceCategory::query()->get(['id', 'name', 'hero_image']) as $category) {
            if (self::matchesId($category->hero_image, $itemId)) {
                $usages[] = 'Kategorie služeb „'.$category->name.'“';
            }
        }

        foreach (TherapistProfile::query()->with('user')->get() as $profile) {
            if (self::matchesId($profile->photo, $itemId)) {
                $usages[] = 'Profil terapeuta „'.($profile->user?->name ?? $profile->slug).'“';
            }
        }

        if (InstagramPost::query()->where('media_library_item_id', $itemId)->exists()) {
            $usages[] = 'Instagramový příspěvek (synchronizovaný obrázek)';
        }

        return $usages;
    }

    /**
     * Whether the JSON brick content references the item — either through a
     * MediaPicker value under one of the known image keys, or through an
     * `<img data-id="…">` inserted into WYSIWYG HTML by the media plugin.
     */
    private static function contentReferences(mixed $content, int $itemId): bool
    {
        if (! is_array($content)) {
            return false;
        }

        foreach ($content as $key => $value) {
            $isImageKey = is_string($key) && in_array($key, self::IMAGE_KEYS, true);

            if (is_array($value)) {
                // A multi-select picker stores a plain list of ids.
                if ($isImageKey && array_any($value, fn (mixed $id): bool => self::matchesId($id, $itemId))) {
                    return true;
                }

                if (self::contentReferences($value, $itemId)) {
                    return true;
                }

                continue;
            }

            if ($isImageKey && self::matchesId($value, $itemId)) {
                return true;
            }

            if (is_string($value) && preg_match('/data-id="'.$itemId.'(?::[^"]*)?"/', $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strictly numeric comparison: MediaPicker state may hydrate as int or
     * numeric string, but non-numeric strings (seeded URLs, user uuids in
     * mention tokens) never match.
     */
    private static function matchesId(mixed $value, int $itemId): bool
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value)))
            && (int) $value === $itemId;
    }
}
