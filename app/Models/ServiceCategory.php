<?php

namespace App\Models;

use App\Contracts\HasPublicPage;
use App\Enums\ServiceType;
use App\Models\Concerns\InteractsWithCustomPage;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model implements HasPublicPage
{
    use HasFactory, HasUuids, InteractsWithCustomPage, Publishable;

    protected $fillable = ['name', 'slug', 'type', 'icon', 'description', 'hero_image', 'published_at'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    /**
     * Canonical public URL for this category.
     */
    public function permalink(): Attribute
    {
        return Attribute::get(fn (): string => route('service-category.show', $this->slug));
    }
}
