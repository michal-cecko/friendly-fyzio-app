<?php

namespace App\Models;

use Database\Factories\BuildingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    /** @use HasFactory<BuildingFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'address'];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
