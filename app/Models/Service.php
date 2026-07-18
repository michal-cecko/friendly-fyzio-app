<?php

namespace App\Models;

use App\Contracts\HasPublicPage;
use App\Enums\ExamType;
use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
use App\Models\Concerns\InteractsWithCustomPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model implements HasPublicPage
{
    use HasFactory, HasUuids, InteractsWithCustomPage, SoftDeletes;

    protected $fillable = [
        'category_id',
        'exam_type',
        'name',
        'invoice_title',
        'slug',
        'icon',
        'duration_minutes',
        'price',
        'break_minutes',
        'visibility',
        'existing_client_months',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'exam_type' => ExamType::class,
            'visibility' => ServiceVisibility::class,
            'published_at' => 'datetime',
            'duration_minutes' => 'integer',
            'price' => 'integer',
            'break_minutes' => 'integer',
            'existing_client_months' => 'integer',
        ];
    }

    /**
     * The service type is owned by its category (single source of truth).
     */
    protected function type(): Attribute
    {
        return Attribute::get(fn (): ?ServiceType => $this->category?->type);
    }

    /**
     * Canonical public URL for this service (nested under its category).
     */
    public function permalink(): Attribute
    {
        return Attribute::get(fn (): string => route('service.show', [
            'category' => $this->category->slug,
            'service' => $this->slug,
        ]));
    }

    /**
     * Services visible on the public website: publicly visible and published.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->where('visibility', ServiceVisibility::Public)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Services a customer can actually book through the reservation wizard:
     * offered online (any non-hidden visibility), published, and performed by at
     * least one therapist. A service nobody performs is a dead end, so it is
     * never offered — the calendar behind it would always be empty.
     */
    public function scopeBookable(Builder $query): Builder
    {
        return $query
            ->where('visibility', '!=', ServiceVisibility::Hidden)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('therapists');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function cancellationRule(): HasOne
    {
        return $this->hasOne(CancellationRule::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'service_rooms');
    }

    public function therapists(): BelongsToMany
    {
        return $this->belongsToMany(TherapistProfile::class, 'service_therapists', 'service_id', 'therapist_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function invitations(): HasMany
    {
        return $this->morphMany(Invitation::class, 'inviteable');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
}
