<?php

namespace App\Filament\Support;

/**
 * How wide the client's window is, as reported by a cookie the panel keeps fresh
 * ({@see resources/views/filament/viewport-hint.blade.php}).
 *
 * The server otherwise has no idea, and a couple of layout decisions have to be
 * made before the first paint — deciding them on the client instead would cost
 * either a round trip or a visible flash of the wrong layout. The cookie is a
 * hint, never a guarantee: it is missing on a first-ever visit and stale for a
 * moment after a resize, so every caller must degrade to the desktop layout.
 */
class Viewport
{
    /** The cookie the panel writes on load and on resize. */
    public const COOKIE = 'fyz_vw';

    /** Below Tailwind's `md`, i.e. phones and small tablets. */
    public const NARROW_BELOW = 768;

    public static function width(): ?int
    {
        $width = (int) request()->cookie(self::COOKIE);

        return $width > 0 ? $width : null;
    }

    public static function isNarrow(): bool
    {
        $width = self::width();

        return $width !== null && $width < self::NARROW_BELOW;
    }
}
