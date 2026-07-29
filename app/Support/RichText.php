<?php

namespace App\Support;

use App\Models\User;
use App\Support\Mentions\StaffMentions;

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
        $html = trim(self::resolveMentions($html));

        if ($html === '') {
            return '';
        }

        if (preg_match('#^<p(?:\s[^>]*)?>(.*)</p>$#s', $html, $matches) && ! str_contains($matches[1], '<p')) {
            return $matches[1];
        }

        return $html;
    }

    /**
     * Collapse rich content to plain text for excerpt contexts (card teasers,
     * meta descriptions) where tags must not leak into an escaped `{{ }}` echo.
     */
    public static function plainText(?string $html): string
    {
        return trim((string) preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags(self::spaceOutBlocks((string) $html)), ENT_QUOTES | ENT_HTML5),
        ));
    }

    /**
     * Insert a space before closing block tags so adjacent paragraphs or list
     * items don't fuse into one word once the markup is stripped.
     */
    private static function spaceOutBlocks(string $html): string
    {
        return (string) preg_replace('#</(p|div|li|h[1-6]|blockquote)>#i', ' </$1>', $html);
    }

    /**
     * Convert plain Textarea text to the equivalent rich-editor HTML: blank
     * lines separate paragraphs, single newlines become <br>. Used when plain
     * text (e.g. a wizard note) lands in a column edited by a RichEditor.
     */
    public static function fromPlainText(?string $text): ?string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        return collect(preg_split('/\R{2,}/', $text) ?: [])
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter()
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e($paragraph), false).'</p>')
            ->implode('');
    }

    /**
     * Replace staff @-mention tokens with public-facing markup: a link to the
     * mentioned person's published therapist profile, or just their plain name
     * when no published profile exists (never leaking unpublished profile URLs).
     * Deleted users fall back to the label stored in the content.
     */
    public static function resolveMentions(?string $html): string
    {
        $html = (string) $html;

        if (! str_contains($html, 'data-type="mention"')) {
            return $html;
        }

        $users = User::query()
            ->whereKey(StaffMentions::extractIds($html))
            ->with('staffProfile')
            ->get()
            ->keyBy(fn (User $user): string => (string) $user->getKey());

        return StaffMentions::replaceMentions($html, function (?string $id, string $storedLabel) use ($users): string {
            $user = $id !== null ? $users->get($id) : null;
            $profile = $user?->staffProfile;
            $name = e($user->name ?? $storedLabel);

            if ($profile && $profile->isPublished() && filled($profile->slug)) {
                return '<a href="'.e($profile->permalink).'">'.$name.'</a>';
            }

            return $name;
        });
    }
}
