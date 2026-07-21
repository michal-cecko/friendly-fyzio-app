<?php

namespace App\Models;

use Database\Factories\TherapistSpecializationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapistSpecialization extends Model
{
    /** @use HasFactory<TherapistSpecializationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'therapist_id',
        'name',
        'icon',
        'description',
        'display_order',
    ];

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'therapist_id');
    }
}
