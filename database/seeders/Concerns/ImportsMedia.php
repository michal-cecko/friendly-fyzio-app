<?php

namespace Database\Seeders\Concerns;

use RalphJSmit\Filament\MediaLibrary\Models\MediaLibraryItem;

trait ImportsMedia
{
    /**
     * Import a remote image into the Media Library (idempotent by caption) and
     * return its item id, so brick MediaPicker fields hold a valid key. Returns
     * null on failure so callers degrade gracefully (card renders without image).
     */
    /**
     * Import a local image file into the Media Library (idempotent by caption)
     * and return its item id. Returns null on failure so callers degrade
     * gracefully.
     */
    protected function mediaFromPath(string $path, string $name): ?int
    {
        $existing = MediaLibraryItem::query()->where('caption', $name)->first();

        if ($existing) {
            return $existing->getKey();
        }

        $item = null;

        try {
            $item = MediaLibraryItem::create(['caption' => $name, 'alt_text' => $name]);
            $item->addMedia($path)->preservingOriginal()->toMediaCollection('library');

            return $item->getKey();
        } catch (\Throwable $e) {
            report($e);
            $item?->forceDelete();

            return null;
        }
    }

    protected function media(string $url, string $name): ?int
    {
        $existing = MediaLibraryItem::query()->where('caption', $name)->first();

        if ($existing) {
            return $existing->getKey();
        }

        $item = null;

        try {
            $item = MediaLibraryItem::create(['caption' => $name, 'alt_text' => $name]);
            $item->addMediaFromUrl($url)->toMediaCollection('library');

            return $item->getKey();
        } catch (\Throwable $e) {
            report($e);
            $item?->forceDelete();

            return null;
        }
    }
}
