<?php

namespace App\Support;

use App\Enums\SettingValueType;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed accessor for application settings.
 *
 * Only primitive data is cached (key => [type, value]) — never Eloquent models —
 * so the cache stays serialization-safe across stores and Octane workers. The cache
 * is invalidated by the Setting model's saved/deleted hooks.
 */
class Settings
{
    public const CACHE_KEY = 'app.settings';

    /**
     * Raw settings keyed by `key`, each as ['type' => string, 'value' => ?string].
     *
     * @return array<string, array{type: string, value: ?string}>
     */
    public static function raw(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()
                ->get(['key', 'type', 'value'])
                ->mapWithKeys(fn (Setting $setting): array => [
                    $setting->key => ['type' => $setting->type->value, 'value' => $setting->value],
                ])
                ->all()
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = self::raw()[$key] ?? null;

        if ($setting === null) {
            return $default;
        }

        return SettingValueType::from($setting['type'])->cast($setting['value']) ?? $default;
    }

    /**
     * The configured length of one reservation block, in minutes.
     */
    public static function blockMinutes(): int
    {
        return (int) self::get('reservation.block_minutes', 15);
    }
}
