<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class Reservation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'client_id',
        'service_id',
        'therapist_id',
        'room_id',
        'reservation_date',
        'start_time',
        'end_time',
        'status',
        'payment_status',
        'confirmation_sent_at',
        'confirmed_at',
        'reminder_sent_at',
        'doctor_note_requested_at',
        'is_control_therapy',
        'notes',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'status' => ReservationStatus::class,
            'payment_status' => PaymentStatus::class,
            'confirmation_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'doctor_note_requested_at' => 'datetime',
            'is_control_therapy' => 'boolean',
        ];
    }

    /**
     * The moment the visit starts, combining the date with the wall-clock start time.
     */
    public function startsAt(): Carbon
    {
        return $this->reservation_date
            ->copy()
            ->setTimeFromTimeString((string) $this->start_time);
    }

    /**
     * Signed magic link (valid until the visit starts) that lets the customer manage
     * their reservation without logging in — confirming, cancelling, or resolving a
     * late-cancel storno decision. Opening it only shows the page; a separate POST
     * performs each action.
     */
    public function manageUrl(): string
    {
        return URL::temporarySignedRoute('reservation.manage', $this->startsAt(), ['reservation' => $this->getKey()]);
    }

    /**
     * Free-cancellation cutoff in hours before the visit: the service's own rule when
     * set, otherwise the clinic-wide default.
     */
    public function cancelBeforeHours(): int
    {
        return $this->service?->cancellationRule?->cancel_before_hours ?? Settings::cancelBeforeHours();
    }

    /**
     * Whether the visit is now inside the storno window (too late for a free online
     * self-cancel). Admin cancellation is not affected by this.
     */
    public function withinStornoWindow(): bool
    {
        return now()->greaterThanOrEqualTo($this->startsAt()->subHours($this->cancelBeforeHours()));
    }

    /**
     * Storno fee for a late cancellation, in whole CZK: a settings-driven percentage
     * of the service price.
     */
    public function stornoFee(): int
    {
        return (int) round(($this->service?->price ?? 0) * Settings::stornoFeePercent() / 100);
    }

    /**
     * Whether cancelling now forces the storno decision (pay / doctor's note /
     * deactivate) instead of a free self-cancel. True for an active reservation that
     * carries a fee once it is either confirmed or already inside the storno window;
     * a Pending reservation still outside the window (or any zero-fee one) stays free.
     */
    public function requiresStornoDecision(): bool
    {
        return in_array($this->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)
            && $this->stornoFee() > 0
            && ($this->status === ReservationStatus::Confirmed || $this->withinStornoWindow());
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class, 'therapist_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function therapyRecord(): HasOne
    {
        return $this->hasOne(TherapyRecord::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function invoice(): HasOne
    {
        return $this->morphOne(Invoice::class, 'invoiceable');
    }
}
