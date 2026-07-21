<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use Database\Factories\TherapistWorkBlockSeriesFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurrence rule for therapist work blocks: weekly (optionally odd/even
 * ISO weeks) on one weekday, materialized into TherapistWorkBlock rows up to
 * generated_until. Open-ended series (ends_on = null) are extended by the
 * work-blocks:extend command; extension only appends dates beyond
 * generated_until, so deleted or edited occurrences are never regenerated.
 */
class TherapistWorkBlockSeries extends Model
{
    /** @use HasFactory<TherapistWorkBlockSeriesFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'therapist_id',
        'room_id',
        'day_of_week',
        'week_type',
        'start_time',
        'end_time',
        'starts_on',
        'ends_on',
        'generated_until',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'week_type' => WeekType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'generated_until' => 'date',
        ];
    }

    /**
     * Times are stored uniformly as `H:i:s` (see TherapistWorkBlock) so the
     * generated child rows always match the parent series' format.
     */
    protected function startTime(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => TherapistWorkBlock::normalizeTime($value));
    }

    protected function endTime(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => TherapistWorkBlock::normalizeTime($value));
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'therapist_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(TherapistWorkBlock::class, 'series_id');
    }
}
