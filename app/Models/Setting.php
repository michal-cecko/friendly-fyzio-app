<?php

namespace App\Models;

use App\Enums\SettingValueType;
use App\Models\Concerns\Auditable;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use Auditable, HasFactory, HasUuids;

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

    public function logTitle(): string
    {
        return $this->label ?: $this->key;
    }

    /**
     * A stable DOM id for this setting's field, used as a scroll anchor so global
     * search can deep-link straight to the field on its settings page. Keys hold
     * dots (`reservation.block_duration`); dots are invalid in a bare URL fragment
     * target, so they collapse to dashes while underscores stay (both id-safe).
     */
    public function anchor(): string
    {
        return 'setting-'.str_replace('.', '-', $this->key);
    }

    /**
     * The stored value decoded into its typed PHP representation.
     */
    protected function typedValue(): Attribute
    {
        return Attribute::get(fn (): mixed => $this->type->cast($this->value));
    }
}
