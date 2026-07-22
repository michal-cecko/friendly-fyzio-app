<?php

namespace App\Support\Mentions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\MentionProvider;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Staff @-mentions inside Filament rich editors: the shared mention provider,
 * extraction of mention tokens from stored TipTap HTML, and the in-app
 * database notification sent to newly mentioned staff members.
 *
 * A mention is persisted by Filament as
 * `<span data-type="mention" data-id="{userId}" data-label="{Name}" data-char="@">@{Name}</span>`
 * (the JS serializer may emit an `<a>` element and a different attribute order).
 */
class StaffMentions
{
    /**
     * Matches a complete mention element including its inner text.
     */
    private const MENTION_ELEMENT_PATTERN = '/<(span|a)\b[^>]*\bdata-type="mention"[^>]*>(.*?)<\/\1>/is';

    public static function editorProvider(): MentionProvider
    {
        return MentionProvider::make('@')
            ->getSearchResultsUsing(fn (string $search): array => self::searchUsers($search))
            ->getLabelsUsing(fn (array $ids): array => User::query()
                ->whereKey($ids)
                ->pluck('name', 'id')
                ->all())
            ->searchPrompt('Začněte psát jméno…')
            ->noSearchResultsMessage('Žádný uživatel nenalezen')
            ->searchingMessage('Hledám…');
    }

    /**
     * Mention dropdown search, case- and diacritics-insensitive ("cec" finds
     * "Čečko"). The mentionable pool is small, so normalization happens in PHP
     * rather than relying on database collations or the unaccent extension.
     *
     * @return array<string, string> id => name
     */
    public static function searchUsers(string $search, int $limit = 10): array
    {
        $needle = self::normalizeForSearch($search);

        return self::staffQuery()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->filter(fn (string $name): bool => str_contains(self::normalizeForSearch($name), $needle))
            ->take($limit)
            ->all();
    }

    /**
     * Cheap check whether the content contains any mention token at all — lets
     * observers bail out before doing any per-save work (relation loads, URL
     * generation) on the overwhelming majority of saves without mentions.
     */
    public static function containsMentions(string|array|null $content): bool
    {
        foreach (self::collectStrings($content) as $string) {
            if (str_contains($string, 'data-type="mention"')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Unique user ids mentioned in the given rich-text HTML.
     *
     * @return array<int, string>
     */
    public static function extractIds(?string $html): array
    {
        $ids = [];

        foreach (self::mentionElements($html) as $element) {
            if (($id = self::attribute($element['tag'], 'data-id')) !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Stored mention labels keyed by user id — the fallback display name when
     * the mentioned user no longer exists.
     *
     * @return array<string, string>
     */
    public static function extractLabels(?string $html): array
    {
        $labels = [];

        foreach (self::mentionElements($html) as $element) {
            $id = self::attribute($element['tag'], 'data-id');

            if ($id === null || isset($labels[$id])) {
                continue;
            }

            $labels[$id] = self::attribute($element['tag'], 'data-label')
                ?? ltrim(trim(self::plainText($element['inner'])), '@');
        }

        return $labels;
    }

    /**
     * A short plain-text excerpt around the first mention of the given user:
     * ~$context characters before, the "@Label" token, ~$context characters after.
     */
    public static function excerptAround(string $html, string $mentionId, int $context = 15): string
    {
        $element = self::firstElementFor($html, $mentionId);

        if ($element === null) {
            return '';
        }

        $before = self::plainText(substr($html, 0, $element['offset']));
        $after = self::plainText(substr($html, $element['offset'] + strlen($element['html'])));
        $label = trim(self::plainText($element['inner']));

        $beforeExcerpt = mb_substr($before, -$context);
        $afterExcerpt = mb_substr($after, 0, $context);

        return (mb_strlen($before) > $context ? '…' : '')
            .$beforeExcerpt
            .$label
            .$afterExcerpt
            .(mb_strlen($after) > $context ? '…' : '');
    }

    /**
     * Replace every mention element in the HTML with the string returned by the
     * callback, which receives the mentioned user id and the stored label.
     *
     * @param  callable(?string $id, string $label): string  $replace
     */
    public static function replaceMentions(?string $html, callable $replace): string
    {
        $html = (string) $html;

        if (! str_contains($html, 'data-type="mention"')) {
            return $html;
        }

        return (string) preg_replace_callback(self::MENTION_ELEMENT_PATTERN, function (array $match) use ($replace): string {
            $tag = substr($match[0], 0, strpos($match[0], '>') + 1);

            $label = self::attribute($tag, 'data-label')
                ?? ltrim(trim(self::plainText($match[2])), '@');

            return $replace(self::attribute($tag, 'data-id'), $label);
        }, $html);
    }

    /**
     * Diff the old and new content of a field and send an in-app database
     * notification to every staff member newly mentioned in it. Content may be
     * an HTML string or a nested array (Mason brick data) whose string values
     * are scanned recursively.
     */
    public static function notifyNewMentions(
        string|array|null $old,
        string|array|null $new,
        ?User $author,
        string $title,
        string $url,
    ): void {
        $newStrings = self::collectStrings($new);

        if ($newStrings === []) {
            return;
        }

        $newIds = self::idsInStrings($newStrings);
        $addedIds = array_diff($newIds, self::idsInStrings(self::collectStrings($old)));

        if ($author !== null) {
            $addedIds = array_diff($addedIds, [(string) $author->getKey()]);
        }

        if ($addedIds === []) {
            return;
        }

        $recipients = self::staffQuery()->whereKey(array_values($addedIds))->get();

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title($title)
                ->body(self::excerptIn($newStrings, (string) $recipient->getKey()))
                ->icon('heroicon-o-at-symbol')
                ->info()
                ->actions([
                    Action::make('open')
                        ->label('Zobrazit')
                        ->url($url),
                ])
                ->sendToDatabase($recipient);
        }
    }

    /**
     * Mentionable users: everyone except customers.
     *
     * @return Builder<User>
     */
    private static function staffQuery(): Builder
    {
        return User::query()->staff();
    }

    /**
     * All string values inside the content, however deeply nested.
     *
     * @return array<int, string>
     */
    private static function collectStrings(string|array|null $content): array
    {
        if ($content === null) {
            return [];
        }

        if (is_string($content)) {
            return $content === '' ? [] : [$content];
        }

        $strings = [];

        array_walk_recursive($content, function (mixed $value) use (&$strings): void {
            if (is_string($value) && $value !== '') {
                $strings[] = $value;
            }
        });

        return $strings;
    }

    /**
     * @param  array<int, string>  $strings
     * @return array<int, string>
     */
    private static function idsInStrings(array $strings): array
    {
        $ids = [];

        foreach ($strings as $string) {
            $ids = array_merge($ids, self::extractIds($string));
        }

        return array_values(array_unique($ids));
    }

    /**
     * The excerpt around the given user's mention, taken from the first string
     * that actually mentions them.
     *
     * @param  array<int, string>  $strings
     */
    private static function excerptIn(array $strings, string $mentionId): string
    {
        foreach ($strings as $string) {
            if (self::firstElementFor($string, $mentionId) !== null) {
                return self::excerptAround($string, $mentionId);
            }
        }

        return '';
    }

    /**
     * All mention elements in the HTML with their byte offsets.
     *
     * @return array<int, array{html: string, tag: string, inner: string, offset: int}>
     */
    private static function mentionElements(?string $html): array
    {
        if ($html === null || ! str_contains($html, 'data-type="mention"')) {
            return [];
        }

        preg_match_all(self::MENTION_ELEMENT_PATTERN, $html, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        return array_map(fn (array $match): array => [
            'html' => $match[0][0],
            'tag' => substr($match[0][0], 0, strpos($match[0][0], '>') + 1),
            'inner' => $match[2][0],
            'offset' => $match[0][1],
        ], $matches);
    }

    /**
     * @return array{html: string, tag: string, inner: string, offset: int}|null
     */
    private static function firstElementFor(string $html, string $mentionId): ?array
    {
        foreach (self::mentionElements($html) as $element) {
            if (self::attribute($element['tag'], 'data-id') === $mentionId) {
                return $element;
            }
        }

        return null;
    }

    private static function attribute(string $tag, string $name): ?string
    {
        return preg_match('/\b'.preg_quote($name, '/').'="([^"]*)"/i', $tag, $match)
            ? html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5)
            : null;
    }

    /**
     * Lowercase and strip diacritics so search compares plain ASCII.
     */
    private static function normalizeForSearch(string $value): string
    {
        return Str::ascii(mb_strtolower(trim($value)));
    }

    /**
     * Collapse HTML to plain text with whitespace runs reduced to single
     * spaces. Boundary whitespace is preserved so excerpt segments keep the
     * space between surrounding words and the mention token.
     */
    private static function plainText(string $html): string
    {
        return (string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }
}
