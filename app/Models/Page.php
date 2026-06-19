<?php

namespace App\Models;

use App\Enums\PageStatus;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'slug',
        'title',
        'system_key',
        'content',
        'status',
        'sort_order',
        'featured_image',
        'meta_title',
        'meta_description',
        'is_system',
        'created_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'status' => PageStatus::class,
            'is_system' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Published);
    }

    public function isHome(): bool
    {
        return $this->system_key === 'home';
    }

    /**
     * The public path for this page ('/' for the homepage).
     */
    public function path(): string
    {
        return $this->isHome() ? '/' : '/'.$this->slug;
    }

    protected static function booted(): void
    {
        static::deleting(fn (Page $page): bool => ! $page->is_system);
    }
}
