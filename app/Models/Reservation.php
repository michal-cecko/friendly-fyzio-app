<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Enums\ConfirmationSource;
use App\Enums\PayableType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Concerns\IsPayable;
use App\Observers\ReservationObserver;
use App\Support\Payments\ReservationPaymentStatus;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

#[ObservedBy(ReservationObserver::class)]
class Reservation extends Model implements Payable
{
    use HasFactory, HasUuids, Prunable, SoftDeletes;
    use IsPayable {
        invoiceItemTemplates as private defaultInvoiceItemTemplates;
    }

    protected static function booted(): void
    {
        // A force-delete (manual or the 30-day trash purge) removes the reservation
        // but never its payments — those outlive it for the accounting record. The
        // link is dropped (payable_id nulled) while payable_type + payable_label keep
        // the history of what the payment was for. The invoice is unlinked the same way.
        static::forceDeleted(function (self $reservation): void {
            $reservation->payments()->update(['payable_id' => null]);
            $reservation->invoice()->update(['invoiceable_id' => null]);
        });
    }

    /**
     * Deleted reservations sit in the trash for 30 days, then the scheduled
     * `model:prune` purges them for good (see {@see booted()} for the payment
     * unlink). Reservations kept as a „storno" record are never soft-deleted, so
     * they are never pruned.
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(30));
    }

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
        'confirmed_by',
        'confirmed_by_id',
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
            'confirmed_by' => ConfirmationSource::class,
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

    public function endsAt(): Carbon
    {
        return $this->reservation_date
            ->copy()
            ->setTimeFromTimeString((string) $this->end_time);
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
     * Whether the visit is already inside the confirmation window (sooner than
     * `confirmationHours` from now). A booking made this close is auto-confirmed: asking
     * the customer to confirm what they just booked is pointless.
     */
    public function withinConfirmationWindow(): bool
    {
        return now()->greaterThanOrEqualTo($this->startsAt()->subHours(Settings::confirmationHours()));
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

    /**
     * The user who confirmed the reservation (customer or staff); null for automatic
     * confirmations. Pairs with the `confirmed_by` source enum.
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
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

    /**
     * Therapy notes written about the client in the context of this reservation
     * (see ClientNote — notes may also exist without a reservation).
     */
    public function clientNotes(): HasMany
    {
        return $this->hasMany(ClientNote::class);
    }

    public function payableType(): PayableType
    {
        return PayableType::Reservation;
    }

    /**
     * A live reservation owes the service price; a cancelled one owes whatever fee
     * was raised for it (storno % or a 100% no-show fee — read from its payments,
     * with the settings-driven storno fee as the fallback when none exists yet).
     */
    public function paymentAmountDue(): int
    {
        if ($this->status === ReservationStatus::Cancelled) {
            return (int) ($this->payments()->sum('amount') ?: $this->stornoFee());
        }

        return (int) ($this->service?->price ?? 0);
    }

    public function payableTitleContext(): array
    {
        return [
            'sluzba' => (string) ($this->service?->invoice_title ?? $this->service?->name ?? ''),
            'datum' => $this->startsAt()->format('d. m. Y'),
            'cas' => $this->startsAt()->format('H:i'),
            'terapeut' => (string) ($this->therapist?->user?->name ?? ''),
            'klient' => (string) ($this->client?->name ?? ''),
        ];
    }

    public function invoiceItemTemplates(): array
    {
        if ($this->status === ReservationStatus::Cancelled) {
            return [
                'title' => (string) Settings::get(PayableType::STORNO_TITLE_KEY, PayableType::STORNO_TITLE_DEFAULT),
                'description' => (string) Settings::get(PayableType::STORNO_DESCRIPTION_KEY, PayableType::STORNO_DESCRIPTION_DEFAULT),
            ];
        }

        return $this->defaultInvoiceItemTemplates();
    }

    /**
     * Recomputes the cached `payment_status` column from the payment records and
     * persists it only when it actually changes. This is the single writer of the
     * column — the create/edit form no longer exposes it, so the status is always a
     * deterministic function of the payments. Reservations track no paid_at column.
     */
    public function recalculatePaymentStatus(): void
    {
        $status = ReservationPaymentStatus::for($this);

        if ($this->payment_status !== $status) {
            $this->forceFill(['payment_status' => $status])->save();
        }
    }

    /** Idempotent Payable hook — for a reservation this is just a recompute. */
    public function markPaymentPaid(): void
    {
        $this->recalculatePaymentStatus();
    }

    public function payableTherapist(): ?User
    {
        return $this->therapist?->user;
    }
}
