<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A spot on the waitlist of a full offer (course series, one-time lesson,
 * workshop) — or, when attached to a Course, a "let me know when registration
 * opens" interest subscription. Entries can be guests (name/email/phone only);
 * the client link is filled when a matching account exists or gets created
 * during promotion.
 */
class WaitlistEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'client_id',
        'waitlistable_type',
        'waitlistable_id',
        'name',
        'email',
        'phone',
        'notified_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function waitlistable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Entries still waiting for a spot, oldest first ("pořadí dle času registrace").
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('notified_at')->oldest();
    }

    public function displayName(): string
    {
        return (string) ($this->client?->name ?? $this->name ?? $this->email ?? '');
    }

    public function displayEmail(): ?string
    {
        $email = $this->client?->email ?? $this->email;

        return filled($email) ? (string) $email : null;
    }

    public function displayPhone(): ?string
    {
        $phone = $this->client?->phone ?? $this->phone;

        return filled($phone) ? (string) $phone : null;
    }
}
