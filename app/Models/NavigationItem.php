<?php

namespace App\Models;

use App\Support\LinkResolver;
use Database\Factories\NavigationItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationItem extends Model
{
    /** @use HasFactory<NavigationItemFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'navigation_id',
        'parent_id',
        'label',
        'link_type',
        'page_id',
        'url',
        'target',
        'display_order',
    ];

    public function navigation(): BelongsTo
    {
        return $this->belongsTo(Navigation::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(NavigationItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(NavigationItem::class, 'parent_id')->orderBy('display_order');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Resolve this item's stored link to a public URL.
     */
    public function resolvedUrl(): ?string
    {
        return LinkResolver::resolve([
            'link_type' => $this->link_type,
            'page_id' => $this->page_id,
            'url' => $this->url,
        ]);
    }
}
