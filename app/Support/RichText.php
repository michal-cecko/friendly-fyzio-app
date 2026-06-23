<?php

namespace App\Support;

/**
 * Helpers for rendering rich-editor (TipTap) HTML stored by Filament's RichEditor
 * on the public site. Content is admin-authored, so it is treated as trusted HTML.
 */
class RichText
{
    /**
     * Unwrap a single enclosing <p> tag so rich content can sit inside an inline
     * or heading context (e.g. an <h2>) without producing invalid nested blocks.
     * Plain strings and multi-paragraph content are returned unchanged.
     */
    public static function inline(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        if (preg_match('#^<p(?:\s[^>]*)?>(.*)</p>$#s', $html, $matches) && ! str_contains($matches[1], '<p')) {
            return $matches[1];
        }

        return $html;
    }
}
