<?php

namespace App\Support\ActivityLog;

use App\Mason\BrickRegistry;
use App\Mason\EmailBrickRegistry;
use Awcodes\Mason\Brick;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Renders a single logged attribute value as something a human can read.
 *
 * Activity rows store raw model state, so a value can be a JSON blob (Mason page
 * content, billing snapshots, settings config), rich-editor HTML, a boolean, or
 * a backed-enum's raw case. This turns each of those into Czech prose:
 * {@see inline()} for one-line contexts (the log list, summary sentences) and
 * {@see rows()} for the detail views, which break a structure open instead of
 * dumping it on one line.
 */
class ActivityValue
{
    /** How many entries of a structure are named before the rest is summarised. */
    private const INLINE_ENTRIES = 4;

    /** Guards against pathologically deep structures. */
    private const MAX_DEPTH = 3;

    /** @var array<string, string>|null Mason brick id → label, built once. */
    private static ?array $brickLabels = null;

    /**
     * A one-line rendering, e.g. "Náš tým, Foto + text +3 další" or
     * "Název: Fit Office s.r.o. · IČO: 19283746".
     *
     * @param  string|null  $key  The attribute name, used for enum casts and nested labels.
     * @param  Model|null  $subject  The logged record, used to resolve enum casts.
     * @param  int  $limit  Truncate the result to this many characters (0 = no limit).
     * @param  array<string, string>  $scope  Field → label overrides for nested keys.
     */
    public static function inline(mixed $value, ?string $key = null, ?Model $subject = null, int $limit = 0, string $placeholder = 'prázdné', array $scope = []): string
    {
        $value = self::decode($value);

        if (self::isEmpty($value)) {
            return $placeholder;
        }

        if (is_bool($value)) {
            return $value ? 'Ano' : 'Ne';
        }

        $text = is_array($value)
            ? self::arrayToInline($value, 1, $scope)
            : self::scalarToInline($value, $key, $subject);

        return $limit > 0 ? Str::limit($text, $limit) : $text;
    }

    /**
     * The value broken into labelled rows for the detail views. Scalars yield a
     * single unlabelled row, so callers can render both shapes the same way.
     *
     * @param  array<string, string>  $scope  Field → label overrides for nested keys.
     * @return list<array{label: ?string, value: string}>
     */
    public static function rows(mixed $value, ?string $key = null, ?Model $subject = null, string $placeholder = '—', array $scope = []): array
    {
        $value = self::decode($value);

        if (self::isEmpty($value)) {
            return [['label' => null, 'value' => $placeholder]];
        }

        if (! is_array($value)) {
            return [['label' => null, 'value' => self::inline($value, $key, $subject, placeholder: $placeholder)]];
        }

        if (self::isBrickList($value)) {
            return self::brickRows($value);
        }

        if (array_is_list($value)) {
            // A list of scalars stays on one line; a list of structures gets a row each.
            if (self::isScalarList($value)) {
                return [['label' => null, 'value' => self::arrayToInline($value, 1, $scope)]];
            }

            return array_values(array_map(
                fn (int $index, mixed $item): array => [
                    'label' => '#'.($index + 1),
                    'value' => self::inline($item, scope: $scope),
                ],
                array_keys($value),
                $value,
            ));
        }

        $rows = [];

        foreach ($value as $childKey => $childValue) {
            $rows[] = [
                'label' => ActivityPresenter::attributeLabel((string) $childKey, $scope),
                'value' => self::inline($childValue, (string) $childKey, placeholder: $placeholder, scope: $scope),
            ];
        }

        return $rows;
    }

    /** Whether {@see rows()} produced anything worth rendering as a nested table. */
    public static function isStructured(mixed $value): bool
    {
        $value = self::decode($value);

        return is_array($value) && ! self::isEmpty($value);
    }

    /**
     * Plain text out of rich-editor HTML: tags dropped, entities decoded, and
     * block boundaries turned into spaces so words don't run together.
     */
    public static function plainText(string $html): string
    {
        $spaced = preg_replace('/<(br|\/p|\/li|\/h[1-6]|\/div|\/tr)[^>]*>/i', ' ', $html) ?? $html;

        return Str::squish(html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5));
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /** JSON stored as a string is unwrapped so it formats like the array it is. */
    private static function decode(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if (! str_starts_with($trimmed, '[') && ! str_starts_with($trimmed, '{')) {
            return $value;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $value;
        }

        return is_array($decoded) ? $decoded : $value;
    }

    private static function scalarToInline(mixed $value, ?string $key, ?Model $subject): string
    {
        if ($key !== null && $subject !== null) {
            $label = self::enumLabel($value, $key, $subject);

            if ($label !== null) {
                return $label;
            }
        }

        $string = (string) $value;

        return $string !== strip_tags($string) ? self::plainText($string) : $string;
    }

    /** Resolves a raw stored value through the model's enum cast, when it has one. */
    private static function enumLabel(mixed $value, string $key, Model $subject): ?string
    {
        $cast = $subject->getCasts()[$key] ?? null;

        if (! is_string($cast) || ! enum_exists($cast) || ! method_exists($cast, 'tryFrom')) {
            return null;
        }

        try {
            $enum = $cast::tryFrom($value);
        } catch (Throwable) {
            return null;
        }

        if ($enum instanceof HasLabel) {
            return (string) $enum->getLabel();
        }

        return $enum !== null && method_exists($enum, 'label') ? (string) $enum->label() : null;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  array<string, string>  $scope
     */
    private static function arrayToInline(array $value, int $depth, array $scope = []): string
    {
        if ($depth > self::MAX_DEPTH) {
            return count($value).' '.self::pluralItems(count($value));
        }

        if (self::isBrickList($value)) {
            return self::summarise(array_map(
                fn (array $node): string => self::brickLabel($node),
                array_values($value),
            ), count($value).' '.self::pluralBlocks(count($value)).': ');
        }

        if (array_is_list($value)) {
            if (self::isScalarList($value)) {
                return self::summarise(array_map(
                    fn (mixed $item): string => self::inline($item, placeholder: '—'),
                    $value,
                ));
            }

            return self::summarise(array_map(
                fn (mixed $item): string => is_array($item) ? self::arrayToInline($item, $depth + 1, $scope) : self::inline($item, placeholder: '—'),
                $value,
            ));
        }

        $pairs = [];

        foreach ($value as $childKey => $childValue) {
            $rendered = is_array($childValue)
                ? self::arrayToInline($childValue, $depth + 1, $scope)
                : self::inline($childValue, (string) $childKey, placeholder: '—');

            $pairs[] = ActivityPresenter::attributeLabel((string) $childKey, $scope).': '.$rendered;
        }

        return self::summarise($pairs, separator: ' · ');
    }

    /**
     * Joins the first few entries and counts the remainder, so a 30-block page
     * reads as "30 bloků: Hero, Náš tým, … +26 dalších" rather than a wall of text.
     *
     * @param  list<string>  $entries
     */
    private static function summarise(array $entries, string $prefix = '', string $separator = ', '): string
    {
        $entries = array_values(array_filter($entries, fn (string $entry): bool => $entry !== ''));
        $shown = array_slice($entries, 0, self::INLINE_ENTRIES);
        $rest = count($entries) - count($shown);

        return $prefix.implode($separator, $shown).($rest > 0 ? ' +'.$rest.' '.self::pluralMore($rest) : '');
    }

    /**
     * A Mason document: a list of `{type: masonBrick, attrs: {id, config}}` nodes,
     * which is what every page/e-mail-template `content` column holds.
     *
     * @param  array<array-key, mixed>  $value
     */
    private static function isBrickList(array $value): bool
    {
        if (! array_is_list($value) || $value === []) {
            return false;
        }

        foreach ($value as $node) {
            if (! is_array($node) || ! isset($node['attrs']['id'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<array-key, mixed>  $nodes
     * @return list<array{label: ?string, value: string}>
     */
    private static function brickRows(array $nodes): array
    {
        $rows = [];

        foreach (array_values($nodes) as $index => $node) {
            $config = $node['attrs']['config'] ?? [];

            $rows[] = [
                'label' => ($index + 1).'. '.self::brickLabel($node),
                'value' => is_array($config) && $config !== []
                    // The brick's own form names its config fields. Depth restarts
                    // here so a repeater nested in the config still unfolds.
                    ? self::arrayToInline($config, 1, FieldLabels::forBrick((string) ($node['attrs']['id'] ?? '')))
                    : '—',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private static function brickLabel(array $node): string
    {
        $id = (string) ($node['attrs']['id'] ?? '');

        return self::brickLabels()[$id] ?? Str::headline($id);
    }

    /** @return array<string, string> */
    private static function brickLabels(): array
    {
        if (self::$brickLabels !== null) {
            return self::$brickLabels;
        }

        $labels = [];

        /** @var class-string<Brick> $brick */
        foreach ([...BrickRegistry::flat(), ...EmailBrickRegistry::flat()] as $brick) {
            $labels[$brick::getId()] = $brick::getLabel();
        }

        return self::$brickLabels = $labels;
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private static function isScalarList(array $value): bool
    {
        foreach ($value as $item) {
            if (is_array($item)) {
                return false;
            }
        }

        return true;
    }

    private static function pluralBlocks(int $count): string
    {
        return match (true) {
            $count === 1 => 'blok',
            $count < 5 => 'bloky',
            default => 'bloků',
        };
    }

    private static function pluralItems(int $count): string
    {
        return match (true) {
            $count === 1 => 'položka',
            $count < 5 => 'položky',
            default => 'položek',
        };
    }

    private static function pluralMore(int $count): string
    {
        return $count < 5 ? 'další' : 'dalších';
    }
}
