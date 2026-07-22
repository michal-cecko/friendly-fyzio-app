<?php

namespace App\Models;

use App\Contracts\HasPermalink;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\Publishable;
use Database\Factories\StaffProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * How a member of staff is presented publicly: their position (`title`), bio,
 * photo, education and certifications. Everyone shown on /o-nas has one —
 * therapists, the conditioning trainer, the yoga instructor and the assistant
 * alike — so holding a profile says nothing about whether the person treats
 * clients.
 *
 * Being bookable is decided elsewhere: {@see User::isTherapist()} plus at least
 * one bookable service ({@see Service::scopeBookable()}). That is why the
 * assistant can appear on the team page while never surfacing in the booking
 * wizard, and why staff who have left keep a profile purely so their historical
 * reservations stay attributed.
 *
 * The `therapist_id` foreign keys that point here are named correctly: whoever
 * sits on a reservation, work block or service link is acting as its therapist.
 */
class StaffProfile extends Model implements HasPermalink
{
    /** @use HasFactory<StaffProfileFactory> */
    use Auditable, HasFactory, HasUuids, Publishable;

    public function logTitle(): string
    {
        return $this->user?->name ?? ('Terapeut #'.substr((string) $this->getKey(), 0, 8));
    }

    protected $fillable = [
        'user_id',
        'slug',
        'bio',
        'title',
        'badge',
        'photo',
        'education',
        'certifications',
        'display_order',
        'is_collaborator',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_collaborator' => 'boolean',
            'education' => 'array',
            'certifications' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (StaffProfile $profile): void {
            if (blank($profile->slug)) {
                $profile->slug = $profile->generateSlug();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specializations(): HasMany
    {
        return $this->hasMany(TherapistSpecialization::class, 'therapist_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_therapists', 'therapist_id', 'service_id');
    }

    /**
     * Profiles that may be offered a NEW booking, the single source of truth for
     * "who is bookable": an active user holding the Therapist capability who
     * performs at least one bookable service. A lecturer or the assistant — a
     * profile with services but no therapist capability — is therefore never
     * offered in the reservation wizard or its slot engine.
     */
    public function scopeBookable(Builder $query): Builder
    {
        return $query
            ->whereHas('user', fn (Builder $user): Builder => $user->whereNull('deactivated_at')->therapists())
            ->whereHas('services', fn (Builder $service): Builder => $service->bookable());
    }

    public function workBlocks(): HasMany
    {
        return $this->hasMany(TherapistWorkBlock::class, 'therapist_id');
    }

    public function workBlockSeries(): HasMany
    {
        return $this->hasMany(TherapistWorkBlockSeries::class, 'therapist_id');
    }

    /**
     * Canonical public URL for this therapist's profile page.
     */
    public function permalink(): Attribute
    {
        return Attribute::get(fn (): string => route('therapist.show', $this->slug));
    }

    /**
     * A unique URL slug derived from the therapist's name.
     */
    protected function generateSlug(): string
    {
        $base = Str::slug($this->user?->name ?? '') ?: 'terapeut';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($this->getKey(), fn ($query, $key) => $query->whereKeyNot($key))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
