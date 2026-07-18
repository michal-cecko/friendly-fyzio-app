<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LessonAttendance extends Model
{
    use Auditable, HasFactory, HasUuids;

    public function logTitle(): string
    {
        return 'Účast na lekci'.($this->enrollment?->client ? ' · '.$this->enrollment->client->name : '');
    }

    protected $fillable = [
        'enrollment_id',
        'lesson_id',
        'attended',
        'cancelled_at',
        'token_generated',
    ];

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
            'cancelled_at' => 'datetime',
            'token_generated' => 'boolean',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'enrollment_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'lesson_id');
    }

    public function substituteToken(): HasOne
    {
        return $this->hasOne(SubstituteToken::class, 'source_lesson_id', 'lesson_id');
    }
}
