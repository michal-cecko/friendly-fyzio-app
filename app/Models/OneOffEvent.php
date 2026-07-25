<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\OfferState;
use App\Enums\OfferVisibility;
use App\Enums\WaitlistPromotionMode;
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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A one-off bookable offer (workshop, jednorázová lekce, …) — the unified
 * replacement for the former Workshop and OneTimeLesson models. The kind of
 * event is its category; an optional course link marks course-derived events
 * (cross-selling, content fallback).
 */
class OneOffEvent extends Model
{
    use Auditable, HasCapacity, HasFactory, HasPresaleAccess, HasUuids, Publishable, SoftDeletes;

    public function logTitle(): string
    {
        return trim($this->name.' · '.$this->event_date?->format('j. n. Y'), ' ·');
    }

    protected $fillable = [
        'event_category_id',
        'course_id',
        'instructor_id',
        'room_id',
        'visibility',
        'presale_token',
        'name',
        'invoice_title',
        'slug',
        'description',
        'featured_image',
        'event_date',
        'start_time',
        'end_time',
        'capacity',
        'waitlist_promotion_mode',
        'waitlist_invited_until',
        'price',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'visibility' => OfferVisibility::class,
            'capacity' => 'integer',
            'waitlist_promotion_mode' => WaitlistPromotionMode::class,
            'waitlist_invited_until' => 'datetime',
            'price' => 'integer',
            'featured_image' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
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
        return $this->hasMany(OneOffEventBooking::class, 'one_off_event_id');
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

    public function invitations()
    {
        return $this->morphMany(Invitation::class, 'inviteable');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('event_date', '>=', today());
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->whereDate('event_date', '<', today());
    }

    /**
     * Description shown on public surfaces — the event's own, or the linked
     * course's as a live fallback (kept in sync, not copied).
     */
    public function displayDescription(): ?string
    {
        return $this->description ?? $this->course?->description;
    }

    /**
     * Media-library image id for public surfaces, falling back to the linked course.
     */
    public function displayImage(): ?int
    {
        return $this->featured_image ?? $this->course?->featured_image;
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->event_date->format('Y-m-d').' '.$this->start_time);
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse($this->event_date->format('Y-m-d').' '.$this->end_time);
    }

    public function isPast(): bool
    {
        return $this->startsAt()->isPast();
    }

    /**
     * Public sign-up state: unpublished or private events are still being
     * prepared, past ones display muted with no sign-up, and published upcoming
     * ones take sign-ups until the capacity runs out (then the waitlist opens).
     */
    public function offerState(): OfferState
    {
        return match (true) {
            ! $this->isPublished() => OfferState::Preparing,
            $this->isPrivate() => OfferState::Preparing,
            $this->isPast() => OfferState::Inactive,
            $this->isFull() => OfferState::Full,
            $this->waitlistInviteActive() => OfferState::Full,
            default => OfferState::Open,
        };
    }

    public function permalink(): string
    {
        return url('/'.$this->category->slug.'/'.$this->slug);
    }
}
