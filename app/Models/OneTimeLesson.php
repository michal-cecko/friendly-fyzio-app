<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\OfferState;
use App\Enums\OfferVisibility;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCapacity;
use App\Models\Concerns\HasPresaleAccess;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class OneTimeLesson extends Model
{
    use Auditable, HasCapacity, HasFactory, HasPresaleAccess, HasUuids, Publishable;

    public function logTitle(): string
    {
        return trim(($this->course?->name ?? 'Lekce').' · '.$this->lesson_date?->format('j. n. Y'), ' ·');
    }

    protected $fillable = [
        'course_id',
        'instructor_id',
        'room_id',
        'visibility',
        'presale_token',
        'invoice_title',
        'lesson_date',
        'start_time',
        'end_time',
        'capacity',
        'auto_promote_waitlist',
        'price',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'lesson_date' => 'date',
            'visibility' => OfferVisibility::class,
            'capacity' => 'integer',
            'auto_promote_waitlist' => 'boolean',
            'price' => 'integer',
            'published_at' => 'datetime',
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

    public function activeTakers(): HasMany
    {
        return $this->bookings()->whereIn('status', BookingStatus::occupying());
    }

    public function waitlistEntries()
    {
        return $this->morphMany(WaitlistEntry::class, 'waitlistable');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('lesson_date', '>=', today());
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->lesson_date->format('Y-m-d').' '.$this->start_time);
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse($this->lesson_date->format('Y-m-d').' '.$this->end_time);
    }

    public function isPast(): bool
    {
        return $this->startsAt()->isPast();
    }

    public function offerState(): OfferState
    {
        return match (true) {
            ! $this->isPublished() => OfferState::Preparing,
            $this->isPrivate() => OfferState::Preparing,
            $this->isPast() => OfferState::Inactive,
            $this->isFull() => OfferState::Full,
            default => OfferState::Open,
        };
    }

    public function permalink(): string
    {
        return url('/kurzy/'.$this->course->slug.'/lekce/'.$this->getKey());
    }
}
