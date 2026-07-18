<?php

namespace App\Models;

use App\Contracts\HasPermalink;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\Publishable;
use Database\Factories\TherapistProfileFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TherapistProfile extends Model implements HasPermalink
{
    /** @use HasFactory<TherapistProfileFactory> */
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
        static::saving(function (TherapistProfile $profile): void {
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
