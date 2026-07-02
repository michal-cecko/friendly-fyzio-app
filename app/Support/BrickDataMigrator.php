<?php

namespace App\Support;

/**
 * One-time transform that folds the legacy per-brick button/link fields into the
 * unified shape used by the shared LinkPickerField / button partial:
 * {text, style, color, icon, link_type, page_id, url}.
 *
 * Every remap is guarded on "old key present AND new key absent" and drops the
 * old key afterwards, so running it repeatedly is a no-op after the first pass.
 */
class BrickDataMigrator
{
    /**
     * Transform a page's Mason content (a list of block arrays) in place.
     *
     * @param  array<int, mixed>  $content
     * @return array<int, mixed>
     */
    public static function migrateContent(array $content): array
    {
        foreach ($content as $index => $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'masonBrick') {
                continue;
            }

            $brickId = $block['attrs']['id'] ?? null;
            $config = $block['attrs']['config'] ?? null;

            if (! is_string($brickId) || ! is_array($config)) {
                continue;
            }

            $content[$index]['attrs']['config'] = self::migrateConfig($brickId, $config);
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected static function migrateConfig(string $brickId, array $config): array
    {
        return match ($brickId) {
            'last-minute' => self::renameButton($config),
            'cards' => self::migrateCards($config),
            'category-cards' => self::migrateCategoryCards($config),
            default => $config,
        };
    }

    /**
     * Fold `button_text`/`button_url` into `text` + `link_type`/`url`.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected static function renameButton(array $config): array
    {
        if (array_key_exists('button_text', $config)) {
            if (! isset($config['text'])) {
                $config['text'] = $config['button_text'];
            }
            unset($config['button_text']);
        }

        if (array_key_exists('button_url', $config)) {
            if (! isset($config['link_type'])) {
                $config['link_type'] = 'custom';
            }
            if (! isset($config['url'])) {
                $config['url'] = $config['button_url'];
            }
            unset($config['button_url']);
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected static function migrateCards(array $config): array
    {
        if (! isset($config['cards']) || ! is_array($config['cards'])) {
            return $config;
        }

        foreach ($config['cards'] as $i => $card) {
            if (! is_array($card)) {
                continue;
            }

            if (array_key_exists('link_text', $card)) {
                if (! isset($card['text'])) {
                    $card['text'] = $card['link_text'];
                }
                unset($card['link_text']);
            }

            $config['cards'][$i] = $card;
        }

        return $config;
    }

    /**
     * Migrate the bottom button plus the inner category and item links.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected static function migrateCategoryCards(array $config): array
    {
        $config = self::renameButton($config);

        if (! isset($config['categories']) || ! is_array($config['categories'])) {
            return $config;
        }

        foreach ($config['categories'] as $ci => $category) {
            if (! is_array($category)) {
                continue;
            }

            $category = self::ensureCustomLinkType($category);

            if (isset($category['items']) && is_array($category['items'])) {
                foreach ($category['items'] as $ii => $item) {
                    if (is_string($item)) {
                        $category['items'][$ii] = ['label' => $item];

                        continue;
                    }

                    if (is_array($item)) {
                        $category['items'][$ii] = self::ensureCustomLinkType($item);
                    }
                }
            }

            $config['categories'][$ci] = $category;
        }

        return $config;
    }

    /**
     * Tag a bare `url` with `link_type = custom` so the LinkPickerField/resolver
     * treats it as a custom URL. Idempotent: skips rows that already have a type.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected static function ensureCustomLinkType(array $row): array
    {
        if (! empty($row['url']) && ! isset($row['link_type'])) {
            $row['link_type'] = 'custom';
        }

        return $row;
    }
}
