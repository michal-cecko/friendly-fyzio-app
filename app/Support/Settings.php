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

    /**
     * Months of inactivity after which a logged-in client must reactivate before
     * booking. Inactivity is measured from the client's latest non-cancelled
     * reservation date.
     */
    public static function reactivationMonths(): int
    {
        return (int) self::get('reservation.reactivation_months', 12);
    }

    /**
     * Fallback recency window (in months) for a `clients`-visibility service that
     * does not set its own `existing_client_months`.
     */
    public static function existingClientMonths(): int
    {
        return (int) self::get('reservation.default_existing_client_months', 6);
    }

    /**
     * How many days into the future the public booking wizard offers slots.
     */
    public static function bookingWindowDays(): int
    {
        return (int) self::get('reservation.booking_window_days', 60);
    }

    /**
     * Minimum lead time (in hours) before a slot can be booked online.
     */
    public static function leadTimeHours(): int
    {
        return (int) self::get('reservation.lead_time_hours', 0);
    }
}
