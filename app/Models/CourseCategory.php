<?php

namespace App\Models;

use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCategory extends Model
{
    use HasFactory, HasUuids, Publishable;

    protected $fillable = ['name', 'slug', 'description', 'published_at', 'display_order'];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'category_id');
    }
}
