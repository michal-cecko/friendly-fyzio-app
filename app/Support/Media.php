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
