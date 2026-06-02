<?php

namespace App\Models;

use Database\Factories\TherapistProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TherapistProfile extends Model
{
    /** @use HasFactory<TherapistProfileFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'bio',
        'is_collaborator',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_collaborator' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specializations(): HasMany
    {
        return $this->hasMany(TherapistSpecialization::class, 'therapist_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_therapists', 'therapist_id', 'service_id');
    }

    public function weeklySchedules(): HasMany
    {
        return $this->hasMany(TherapistWeeklySchedule::class, 'therapist_id');
    }

    public function nonstandardDates(): HasMany
    {
        return $this->hasMany(TherapistNonstandardDate::class, 'therapist_id');
    }

    public function calendarBlocks(): HasMany
    {
        return $this->hasMany(CalendarBlock::class, 'therapist_id');
    }
}
