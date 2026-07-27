<?php

namespace App\Support;

use Illuminate\Support\Collection;

class Avatar
{
    /**
     * Two-letter initials for an avatar fallback, skipping academic-title words
     * ("Mgr.", "Bc."): "Mgr. Lucie Fičkerová" → "LF". Falls back to the first two
     * characters when a name has no title-free words, or "?" when blank.
     */
    public static function initials(?string $name): string
    {
        if (blank($name)) {
            return '?';
        }

        $words = array_values(array_filter(
            preg_split('/\s+/', trim($name)) ?: [],
            fn (string $word): bool => $word !== '' && ! str_ends_with($word, '.'),
        ));

        if ($words === []) {
            return mb_strtoupper(mb_substr($name, 0, 2));
        }

        return mb_strtoupper(
            (new Collection($words))->take(2)->map(fn (string $word): string => mb_substr($word, 0, 1))->implode('')
        );
    }

    /**
     * The given name alone, skipping academic-title words the same way initials
     * do: "Mgr. Lucie Fičkerová" → "Lucie". Falls back to the whole name.
     */
    public static function firstName(?string $name): string
    {
        if (blank($name)) {
            return '';
        }

        $words = array_values(array_filter(
            preg_split('/\s+/', trim($name)) ?: [],
            fn (string $word): bool => $word !== '' && ! str_ends_with($word, '.'),
        ));

        return $words[0] ?? trim($name);
    }
}
