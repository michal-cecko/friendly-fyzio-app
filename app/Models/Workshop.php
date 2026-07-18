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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Workshop extends Model
{
    use Auditable, HasCapacity, HasFactory, HasPresaleAccess, HasUuids, Publishable, SoftDeletes;

    protected $fillable = [
        'instructor_id',
        'room_id',
        'visibility',
        'presale_token',
        'name',
        'invoice_title',
        'slug',
        'description',
        'featured_image',
        'workshop_date',
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
            'workshop_date' => 'date',
            'visibility' => OfferVisibility::class,
            'capacity' => 'integer',
            'auto_promote_waitlist' => 'boolean',
            'price' => 'integer',
            'featured_image' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(WorkshopRegistration::class);
    }

    public function activeTakers(): HasMany
    {
        return $this->registrations()->whereIn('status', BookingStatus::occupying());
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
        return $query->whereDate('workshop_date', '>=', today());
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->whereDate('workshop_date', '<', today());
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->workshop_date->format('Y-m-d').' '.$this->start_time);
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse($this->workshop_date->format('Y-m-d').' '.$this->end_time);
    }

    public function isPast(): bool
    {
        return $this->startsAt()->isPast();
    }

    /**
     * Public registration state: unpublished workshops are still being prepared,
     * past ones display muted with no registration, and published upcoming ones
     * take registrations until the capacity runs out (then the waitlist opens).
     */
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
        return url('/workshopy/'.$this->slug);
    }
}
