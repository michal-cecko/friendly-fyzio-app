<?php

namespace App\Support;

use App\Models\Page;

/**
 * Resolves a stored link into a public URL. Reused by Mason bricks, navigation
 * items and banners.
 *
 * New shape: {link_type: 'internal'|'custom', link_ref, url} where link_ref is a
 * reference string understood by {@see InternalLinks}. The legacy shape
 * ({link_type: 'page', page_id}) is still honoured so existing content keeps
 * resolving until it's re-saved.
 */
class LinkResolver
{
    /**
     * @param  array{link_type?: ?string, link_ref?: ?string, page_id?: ?string, url?: ?string}  $link
     */
    public static function resolve(array $link): ?string
    {
        if (! empty($link['link_ref'])) {
            return InternalLinks::resolve($link['link_ref']);
        }

        // Legacy: an internal Page referenced by id.
        $type = $link['link_type'] ?? 'custom';
        if (in_array($type, ['page', 'internal'], true) && ! empty($link['page_id'])) {
            return Page::find($link['page_id'])?->permalink;
        }

        $url = $link['url'] ?? null;

        return $url !== null && $url !== '' ? $url : null;
    }

    /**
     * Resolve a link stored under a prefixed key set (e.g. "cta_link_type",
     * "cta_link_ref", "cta_url") within a brick config array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config, string $prefix = ''): ?string
    {
        return self::resolve([
            'link_type' => $config["{$prefix}link_type"] ?? null,
            'link_ref' => $config["{$prefix}link_ref"] ?? null,
            'page_id' => $config["{$prefix}page_id"] ?? null,
            'url' => $config["{$prefix}url"] ?? null,
        ]);
    }
}
