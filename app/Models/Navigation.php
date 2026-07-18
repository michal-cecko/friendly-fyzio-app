<?php

namespace App\Models;

use App\Enums\NavigationLocation;
use App\Models\Concerns\Auditable;
use Database\Factories\NavigationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Navigation extends Model
{
    /** @use HasFactory<NavigationFactory> */
    use Auditable, HasFactory, HasUuids;

    public function logTitle(): string
    {
        return 'Navigace · '.($this->location?->getLabel() ?? '');
    }

    protected $fillable = ['location'];

    protected function casts(): array
    {
        return [
            'location' => NavigationLocation::class,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(NavigationItem::class)->whereNull('parent_id')->orderBy('display_order');
    }
}
