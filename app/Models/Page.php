<?php

namespace App\Models;

use App\Contracts\HasPermalink;
use App\Models\Concerns\Publishable;
use App\Observers\PageObserver;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(PageObserver::class)]
class Page extends Model implements HasPermalink
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasUuids, Publishable, SoftDeletes;

    protected $fillable = [
        'slug',
        'title',
        'system_key',
        'content',
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
            'is_system' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The model this page is the public page for (e.g. a ServiceCategory), if any.
     */
    public function pageable(): MorphTo
    {
        return $this->morphTo();
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

    /**
     * Canonical public URL. When this page is attached to an owner (pageable),
     * the owner's permalink wins so the two URLs can never diverge.
     */
    public function permalink(): Attribute
    {
        return Attribute::get(fn (): string => $this->pageable?->permalink ?? url($this->path()));
    }

    protected static function booted(): void
    {
        static::deleting(fn (Page $page): bool => ! $page->is_system);
    }
}
