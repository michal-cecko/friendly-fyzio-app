<?php

namespace App\Support;

use App\Models\Page;

/**
 * Resolves a stored link array ({link_type, page_id, url}) into a public URL.
 * Reused by Mason bricks, navigation items and banners.
 */
class LinkResolver
{
    /**
     * @param  array{link_type?: ?string, page_id?: ?string, url?: ?string}  $link
     */
    public static function resolve(array $link): ?string
    {
        $type = $link['link_type'] ?? 'custom';

        if ($type === 'page' && ! empty($link['page_id'])) {
            $page = Page::find($link['page_id']);

            return $page ? url($page->path()) : null;
        }

        $url = $link['url'] ?? null;

        return $url !== '' ? $url : null;
    }

    /**
     * Resolve a link stored under a prefixed key set (e.g. "cta_link_type",
     * "cta_page_id", "cta_url") within a brick config array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config, string $prefix = ''): ?string
    {
        return self::resolve([
            'link_type' => $config["{$prefix}link_type"] ?? null,
            'page_id' => $config["{$prefix}page_id"] ?? null,
            'url' => $config["{$prefix}url"] ?? null,
        ]);
    }
}
