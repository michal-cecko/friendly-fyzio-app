<?php

namespace App\Models;

use Database\Factories\SpecializationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shared, reusable specialization defined under a {@see Service}. Therapists
 * reference these from their profile instead of retyping name/icon/description.
 */
class Specialization extends Model
{
    /** @use HasFactory<SpecializationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'service_id',
        'name',
        'icon',
        'description',
        'display_order',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function therapistSpecializations(): HasMany
    {
        return $this->hasMany(TherapistSpecialization::class);
    }
}
