<?php

namespace App\Models;

use App\Enums\LessonExcuseReason;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LessonAttendance extends Model
{
    use Auditable, HasFactory, HasUuids;

    /**
     * Naming the enrollment (or the booking) already names the client, so the
     * seat fills its own owner in rather than making every caller repeat it.
     */
    protected static function booted(): void
    {
        static::creating(function (self $attendance): void {
            if ($attendance->client_id !== null) {
                return;
            }

            $attendance->client_id = $attendance->enrollment?->client_id
                ?? $attendance->booking?->client_id;
        });
    }

    public function logTitle(): string
    {
        return 'Účast na lekci'.($this->client ? ' · '.$this->client->name : '');
    }

    protected $fillable = [
        'client_id',
        'enrollment_id',
        'booking_id',
        'lesson_id',
        'attended',
        'cancelled_at',
        'excuse_reason',
        'excuse_note',
        'excused_by_id',
        'replacement_attendance_id',
        'token_generated',
    ];

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
            'cancelled_at' => 'datetime',
            'excuse_reason' => LessonExcuseReason::class,
            'token_generated' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Set when this seat comes with a place in the course série; null for
     * somebody who bought the single lesson.
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'enrollment_id');
    }

    /**
     * Set when this seat was bought on its own — the drop-in purchase behind it.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(LessonBooking::class, 'booking_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    public function excusedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excused_by_id');
    }

    public function substituteToken(): HasOne
    {
        return $this->hasOne(SubstituteToken::class, 'source_attendance_id');
    }

    /**
     * The row that makes this missed lesson up — set once the client redeems a
     * náhrada or staff move them somewhere else.
     */
    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replacement_attendance_id');
    }

    /**
     * The missed lesson this row is the náhrada for — the inverse of
     * {@see self::replacement()}.
     */
    public function replacementFor(): HasOne
    {
        return $this->hasOne(self::class, 'replacement_attendance_id');
    }

    public function isExcused(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Somebody who bought this one lesson rather than the whole série. None of
     * the course's náhrada machinery applies to them — they paid for a seat, so
     * cancelling is a refund, not a poukaz.
     */
    public function isDropIn(): bool
    {
        return $this->booking_id !== null;
    }

    /**
     * Substitutes sit in the lesson on an enrollment from another série, so the
     * course's own excuse rules (and its make-up entitlement) don't apply to them.
     */
    public function isSubstituteGuest(): bool
    {
        return $this->enrollment !== null
            && $this->enrollment->series_id !== $this->lesson?->series_id;
    }
}
