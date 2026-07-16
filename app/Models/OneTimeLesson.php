<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OneTimeLesson extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id',
        'instructor_id',
        'room_id',
        'invoice_title',
        'lesson_date',
        'start_time',
        'end_time',
        'capacity',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'lesson_date' => 'date',
            'capacity' => 'integer',
            'price' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(OneTimeLessonBooking::class, 'lesson_id');
    }

    public function waitlistEntries()
    {
        return $this->morphMany(WaitlistEntry::class, 'waitlistable');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
}
