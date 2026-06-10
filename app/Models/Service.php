<?php

namespace App\Models;

use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'duration_minutes',
        'price',
        'break_minutes',
        'visibility',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => ServiceVisibility::class,
            'published_at' => 'datetime',
            'duration_minutes' => 'integer',
            'price' => 'integer',
            'break_minutes' => 'integer',
        ];
    }

    /**
     * The service type is owned by its category (single source of truth).
     */
    protected function type(): Attribute
    {
        return Attribute::get(fn (): ?ServiceType => $this->category?->type);
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
}
