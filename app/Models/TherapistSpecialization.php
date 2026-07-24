<?php

namespace App\Models;

use Database\Factories\TherapistSpecializationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A therapist's ({@see StaffProfile}) chosen specialization: a thin link to a
 * catalog {@see Specialization} plus a per-therapist ordering. Name, icon and
 * description are derived from the linked catalog entry, so they are defined
 * once (under a service) rather than retyped for every therapist.
 */
class TherapistSpecialization extends Model
{
    /** @use HasFactory<TherapistSpecializationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'therapist_id',
        'specialization_id',
        'display_order',
    ];

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'therapist_id');
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    /**
     * Proxy the catalog entry's name so existing callers (`$spec->name`) keep
     * working after the column moved to {@see Specialization}.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->specialization?->name);
    }

    protected function icon(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->specialization?->icon);
    }

    protected function description(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->specialization?->description);
    }
}
