<?php

namespace App\Models;

use Database\Factories\TherapistWorkBlockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A concrete dated working block of a therapist in a room. One-off blocks
 * stand alone; blocks generated from a recurrence pattern reference their
 * TherapistWorkBlockSeries via series_id.
 */
class TherapistWorkBlock extends Model
{
    /** @use HasFactory<TherapistWorkBlockFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'therapist_id',
        'room_id',
        'series_id',
        'work_date',
        'start_time',
        'end_time',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    /**
     * The date is stored uniformly as `Y-m-d` (matching WorkBlockGenerator's
     * bulk inserts) so sqlite's lexical text comparison of work_date behaves
     * like pgsql's native date semantics.
     */
    protected function workDate(): Attribute
    {
        return Attribute::set(fn (mixed $value): ?string => $value === null
            ? null
            : Carbon::parse($value)->toDateString());
    }

    /**
     * Times are stored uniformly as `H:i:s` so sqlite's lexical text comparison
     * of the time columns matches pgsql's native time semantics.
     */
    protected function startTime(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::normalizeTime($value));
    }

    protected function endTime(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::normalizeTime($value));
    }

    public static function normalizeTime(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return strlen($time) === 5 ? "{$time}:00" : $time;
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'therapist_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(TherapistWorkBlockSeries::class, 'series_id');
    }

    /**
     * Blocks of the given therapist on the given date overlapping the
     * [start, end) time interval — a therapist works in one room at a time,
     * so overlaps are rejected regardless of room.
     *
     * @return Builder<self>
     */
    public static function overlapsQuery(string $therapistId, string $date, string $startTime, string $endTime): Builder
    {
        // Normalize `H:i` input to `H:i:s` so sqlite's lexical time comparison
        // treats blocks that merely touch (end == next start) as non-overlapping.
        $startTime = self::normalizeTime($startTime);
        $endTime = self::normalizeTime($endTime);

        return self::query()
            ->where('therapist_id', $therapistId)
            ->whereDate('work_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);
    }
}
