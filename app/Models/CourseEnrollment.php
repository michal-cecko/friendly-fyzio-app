<?php

namespace App\Models;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseEnrollment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'client_id',
        'series_id',
        'status',
        'payment_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseEnrollmentStatus::class,
            'payment_status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(CourseSeries::class, 'series_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class, 'enrollment_id');
    }
}
