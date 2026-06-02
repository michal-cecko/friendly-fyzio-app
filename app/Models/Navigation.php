<?php

namespace App\Models;

use Database\Factories\NavigationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Navigation extends Model
{
    /** @use HasFactory<NavigationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['location'];

    public function items(): HasMany
    {
        return $this->hasMany(NavigationItem::class)->whereNull('parent_id')->orderBy('display_order');
    }
}
