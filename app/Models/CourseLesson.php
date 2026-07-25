<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Observers\CourseLessonObserver;
use App\Support\Substitutes\MoveClientToLesson;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[ObservedBy(CourseLessonObserver::class)]
class CourseLesson extends Model
{
    use Auditable, HasFactory, HasUuids;

    public function logTitle(): string
    {
        return trim(($this->series?->course?->name ?? $this->series?->name ?? 'Lekce').' · '.$this->lesson_date?->format('j. n. Y'), ' ·');
    }

    protected $fillable = [
        'series_id',
        'instructor_id',
        'room_id',
        'lesson_date',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'lesson_date' => 'date',
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(CourseSeries::class, 'series_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class, 'lesson_id');
    }

    public function substituteTokensAsSource(): HasMany
    {
        return $this->hasMany(SubstituteToken::class, 'source_lesson_id');
    }

    public function substituteTokensUsedHere(): HasMany
    {
        return $this->hasMany(SubstituteToken::class, 'used_for_lesson_id');
    }

    /**
     * A lesson holds no capacity of its own — it borrows the série's room.
     */
    protected function capacity(): Attribute
    {
        return Attribute::get(fn (): int => (int) ($this->series?->capacity ?? 0));
    }

    /**
     * People expected in the room for this particular lesson: everyone actively
     * enrolled in the série, minus those excused from this date, plus substitutes
     * booked in from elsewhere.
     *
     * Substitutes are counted from the attendance rows themselves — non-cancelled
     * attendances whose enrollment belongs to a *different* série than the lesson.
     * That captures both client-zone token redemptions and manual staff overrides
     * ({@see MoveClientToLesson}), which place a substitute
     * without minting a token.
     */
    public function takenSpots(): int
    {
        return max(0, $this->enrolledCount() - $this->excusedCount() + $this->substitutesInCount());
    }

    public function spotsLeft(): int
    {
        return max(0, $this->capacity - $this->takenSpots());
    }

    public function isFull(): bool
    {
        return $this->spotsLeft() === 0;
    }

    /**
     * Active enrollments in the owning série.
     */
    public function enrolledCount(): int
    {
        return (int) ($this->series?->takenSpots() ?? 0);
    }

    /**
     * Clients excused from this date, whose spot is therefore free.
     */
    public function excusedCount(): int
    {
        $eager = $this->getAttribute('excused_count');

        return $eager !== null
            ? (int) $eager
            : $this->attendances()->whereNotNull('cancelled_at')->count();
    }

    /**
     * Substitutes from another série sitting in this lesson.
     */
    public function substitutesInCount(): int
    {
        $eager = $this->getAttribute('substitutes_in_count');

        return $eager !== null
            ? (int) $eager
            : $this->attendances()
                ->whereNull('cancelled_at')
                ->whereHas('enrollment', fn (Builder $query) => $query->where('series_id', '!=', $this->series_id))
                ->count();
    }

    /**
     * Eager-loads everything {@see takenSpots()} needs, so lesson lists don't fire
     * three COUNTs per row.
     */
    public function scopeWithOccupancyCounts(Builder $query): Builder
    {
        return $query
            ->with(['series' => fn ($series) => $series->withTakenSpots()])
            ->withCount([
                'attendances as excused_count' => fn (Builder $attendances) => $attendances->whereNotNull('cancelled_at'),
                'attendances as substitutes_in_count' => fn (Builder $attendances) => $attendances
                    ->whereNull('cancelled_at')
                    ->whereHas('enrollment', fn (Builder $enrollment) => $enrollment
                        ->whereColumn('course_enrollments.series_id', '!=', 'course_lessons.series_id')),
            ]);
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->lesson_date->format('Y-m-d').' '.$this->start_time);
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse($this->lesson_date->format('Y-m-d').' '.$this->end_time);
    }
}
