<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->lesson_date->format('Y-m-d').' '.$this->start_time);
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse($this->lesson_date->format('Y-m-d').' '.$this->end_time);
    }
}
