<?php

namespace App\Models;

use App\Enums\BannerType;
use App\Models\Concerns\Auditable;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'placement',
        'page_ids',
        'content',
        'is_active',
        'active_from',
        'active_to',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => BannerType::class,
            'content' => 'array',
            'page_ids' => 'array',
            'is_active' => 'boolean',
            'active_from' => 'datetime',
            'active_to' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q): Builder => $q->whereNull('active_from')->orWhere('active_from', '<=', now()))
            ->where(fn (Builder $q): Builder => $q->whereNull('active_to')->orWhere('active_to', '>=', now()));
    }

    public function scopeForPage(Builder $query, ?string $pageId): Builder
    {
        return $query->where(function (Builder $q) use ($pageId): void {
            $q->where('placement', 'all');

            if ($pageId) {
                $q->orWhere(fn (Builder $inner): Builder => $inner
                    ->where('placement', 'specific')
                    ->whereJsonContains('page_ids', $pageId));
            }
        });
    }

    public function scopeOfType(Builder $query, BannerType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * The highest-priority active banner of a given type for a page (or null).
     */
    public static function resolve(BannerType $type, ?string $pageId): ?self
    {
        return static::query()
            ->active()
            ->forPage($pageId)
            ->ofType($type)
            ->orderByDesc('sort_order')
            ->first();
    }
}
