<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use Database\Factories\TherapistWeeklyScheduleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapistWeeklySchedule extends Model
{
    /** @use HasFactory<TherapistWeeklyScheduleFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'therapist_id',
        'day_of_week',
        'week_type',
        'start_time',
        'end_time',
        'room_id',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'week_type' => WeekType::class,
        ];
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class, 'therapist_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
