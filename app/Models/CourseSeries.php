<?php

namespace App\Models;

use App\Enums\CourseSeriesStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSeries extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id',
        'name',
        'invoice_title',
        'start_date',
        'end_date',
        'capacity',
        'price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'capacity' => 'integer',
            'price' => 'integer',
            'status' => CourseSeriesStatus::class,
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class, 'series_id')->orderBy('lesson_date');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'series_id');
    }

    public function waitlistEntries()
    {
        return $this->morphMany(WaitlistEntry::class, 'waitlistable');
    }

    public function invitations()
    {
        return $this->morphMany(Invitation::class, 'inviteable');
    }
}
