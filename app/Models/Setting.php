<?php

namespace App\Models;

use App\Enums\SettingValueType;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['key', 'value', 'type', 'label', 'group', 'description', 'config', 'sort'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => SettingValueType::class,
            'config' => 'array',
            'sort' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(Settings::CACHE_KEY);

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * The stored value decoded into its typed PHP representation.
     */
    protected function typedValue(): Attribute
    {
        return Attribute::get(fn (): mixed => $this->type->cast($this->value));
    }
}
