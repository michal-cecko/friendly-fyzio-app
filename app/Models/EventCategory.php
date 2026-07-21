<?php

namespace App\Models;

use App\Contracts\HasPublicPage;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\InteractsWithCustomPage;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Taxonomy of one-off events ("Workshopy", "Jednorázové lekce", …). Each
 * category is a public landing page at /{slug} — data-driven by default,
 * overridable with a custom Mason page via `pageable`.
 */
class EventCategory extends Model implements HasPublicPage
{
    use Auditable, HasFactory, HasUuids, InteractsWithCustomPage, Publishable;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'featured_image',
        'display_order',
        'published_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'featured_image' => 'integer',
            'display_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(OneOffEvent::class, 'event_category_id');
    }

    /**
     * Canonical public URL for this category (top-level slug).
     */
    public function permalink(): Attribute
    {
        return Attribute::get(fn (): string => url('/'.$this->slug));
    }
}
