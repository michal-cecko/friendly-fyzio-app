<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A spot on the "pořadník" for a fully-booked day in the reservation wizard. The
 * scope is a therapist's day — (therapist-or-"any", date) — never a service: a
 * therapist does many things, so any freed slot on that day counts. `service_id`
 * only records what the customer was browsing, to prefill the booking link.
 *
 * Guest-capable (name/email/phone); `client_id` is linked when a matching account
 * exists. `notified_at` marks that the "spot available" e-mail has gone out.
 */
class ReservationDayWaitlistEntry extends Model
{
    use Auditable, HasFactory, HasUuids, Prunable;

    public function logTitle(): string
    {
        return 'Pořadník (den) · '.$this->displayName();
    }

    protected $fillable = [
        'client_id',
        'therapist_id',
        'service_id',
        'reservation_date',
        'name',
        'email',
        'phone',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'notified_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'therapist_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Entries still waiting for a spot, oldest first.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('notified_at')->oldest();
    }

    /**
     * Past-date entries are dead weight — the scheduled `model:prune` clears them.
     */
    public function prunable(): Builder
    {
        return static::query()->whereDate('reservation_date', '<', today());
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

    /**
     * The therapist's display name, or the "any therapist" label for an open scope.
     */
    public function therapistLabel(): string
    {
        return $this->therapist?->user?->name ?? 'Libovolný terapeut';
    }
}
