<?php

namespace App\Models;

use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'slug', 'type'];

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
}
