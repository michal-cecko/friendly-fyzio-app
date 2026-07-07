<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workshop extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'instructor_id',
        'room_id',
        'name',
        'slug',
        'description',
        'workshop_date',
        'start_time',
        'end_time',
        'capacity',
        'price',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'workshop_date' => 'date',
            'capacity' => 'integer',
            'price' => 'integer',
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

    public function invoice()
    {
        return $this->morphOne(Invoice::class, 'invoiceable');
    }
}
