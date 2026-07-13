<?php

namespace App\Filament\Support\Search;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Per-user list of recently executed admin search terms, kept in the cache.
 */
class RecentSearches
{
    public const LIMIT = 10;

    public const TTL_DAYS = 30;

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return Cache::get($this->cacheKey(), []);
    }

    public function record(string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $terms = array_filter(
            $this->all(),
            // Drop duplicates and earlier, shorter prefixes of the same term ("ja", "jan" → "jan n").
            fn (string $existing): bool => ! str_starts_with(mb_strtolower($term), mb_strtolower($existing))
                && mb_strtolower($existing) !== mb_strtolower($term),
        );

        array_unshift($terms, $term);

        $this->put(array_slice(array_values($terms), 0, self::LIMIT));
    }

    public function forget(string $term): void
    {
        $this->put(array_values(array_filter(
            $this->all(),
            fn (string $existing): bool => mb_strtolower($existing) !== mb_strtolower($term),
        )));
    }

    public function clear(): void
    {
        Cache::forget($this->cacheKey());
    }

    /**
     * @param  array<int, string>  $terms
     */
    protected function put(array $terms): void
    {
        Cache::put($this->cacheKey(), $terms, now()->addDays(self::TTL_DAYS));
    }

    protected function cacheKey(): string
    {
        return 'admin.recent-searches.'.Auth::id();
    }
}
