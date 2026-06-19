<?php

namespace App\Support;

use RalphJSmit\Filament\MediaLibrary\Models\MediaLibraryItem;

/**
 * Resolves a Filament Media Library item id (as stored by MediaPicker) into a
 * public URL, optionally for a named conversion ('thumb', '400', '800').
 */
class Media
{
    public static function url(int|string|null $id, ?string $conversion = null): ?string
    {
        if (blank($id)) {
            return null;
        }

        // Allow direct URLs / absolute paths (used by seeded design content),
        // while admin-picked values are media-library item ids.
        if (is_string($id) && (str_starts_with($id, 'http') || str_starts_with($id, '/'))) {
            return $id;
        }

        $item = MediaLibraryItem::query()->find($id);

        $media = $item?->getItem();

        if (! $media) {
            return null;
        }

        if ($conversion && $media->hasGeneratedConversion($conversion)) {
            return $media->getUrl($conversion);
        }

        return $media->getUrl();
    }
}
