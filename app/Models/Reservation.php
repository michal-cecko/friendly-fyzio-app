<?php

namespace App\Models;

use App\Contracts\Emailable;
use App\Contracts\Payable;
use App\Enums\ConfirmationSource;
use App\Enums\EmailTemplateKey;
use App\Enums\PayableType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationDocumentType;
use App\Enums\ReservationStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsPayable;
use App\Observers\ReservationObserver;
use App\Support\ActivityLog\LogActivity;
use App\Support\Emails\CopyRecipients;
use App\Support\Emails\ReservationEmailer;
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
class Reservation extends Model implements Emailable, Payable
{
    use Auditable, HasFactory, HasUuids, Prunable, SoftDeletes;

    /**
     * The columns whose change counts as a termín change worth notifying the client
     * and therapist about — when (date/time), where (room) and who (therapist).
     * Shared by the reservation edit page and the calendar edit modal so their
     * "notify on change" prompt fires on the same edits.
     *
     * @var array<int, string>
     */
    public const SCHEDULE_ATTRIBUTES = ['reservation_date', 'start_time', 'end_time', 'room_id', 'therapist_id'];

    public function logTitle(): string
    {
        return trim(
            ($this->client?->name ?? 'Rezervace')
            .' · '.($this->service?->name ?? '')
            .' · '.$this->reservation_date?->format('j. n. Y'),
            ' ·',
        );
    }

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
        'break_minutes',
        'status',
        'payment_status',
        'confirmation_sent_at',
        'confirmed_at',
        'confirmed_by',
        'confirmed_by_id',
        'reminder_sent_at',
        'doctor_note_requested_at',
        'doctor_note_resolved_at',
        'settled_at',
        'imported_at',
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
            'doctor_note_resolved_at' => 'datetime',
            'settled_at' => 'datetime',
            'imported_at' => 'datetime',
            'is_control_therapy' => 'boolean',
            'break_minutes' => 'integer',
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
     * When the therapist is actually free again: the visit's end plus the break
     * frozen onto this reservation when it was booked. This — not {@see endsAt()}
     * — is the „Do" shown in the schedule, because the slot after it is what the
     * next client can have.
     */
    public function endsAtIncludingBreak(): Carbon
    {
        return $this->endsAt()->addMinutes($this->break_minutes);
    }

    /**
     * Human note naming the break folded into {@see endsAtIncludingBreak()},
     * or null when there is none.
     */
    public function breakLabel(): ?string
    {
        return $this->break_minutes > 0
            ? 'vč. '.$this->break_minutes.' min pauzy'
            : null;
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
     * How long the doctor-note upload link stays valid, counted from the moment the
     * note was promised. Deliberately generous — a client who cancelled because they
     * are ill may only get to a doctor days later.
     */
    public const DOCTOR_NOTE_UPLOAD_DAYS = 14;

    /**
     * Signed link to the passwordless page where the client uploads their doctor's
     * note. Separate from {@see manageUrl()} because that one expires when the visit
     * starts, which is always before the note can arrive.
     */
    public function doctorNoteUploadUrl(): string
    {
        return URL::temporarySignedRoute(
            'reservation.doctor-note',
            ($this->doctor_note_requested_at ?? now())->copy()->addDays(self::DOCTOR_NOTE_UPLOAD_DAYS),
            ['reservation' => $this->getKey()],
        );
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

    /**
     * Reservations the client still has to act on — an unresolved doctor's note, an
     * open payment, or a past visit that was never marked paid. This is what keeps a
     * late storno (or an unpaid visit) in the client zone's „Aktivní" tab instead of
     * dropping it straight into the archive.
     *
     * Deliberately a superset of what {@see ClientReservationState::needsAttention()}
     * reports, so no row can ever land in „Dokončené" while its badge still says
     * „Čeká na…". The precise grouping is done in PHP off the loaded models.
     */
    public function scopeNeedsClientAttention(Builder $query): void
    {
        $query->whereNull('settled_at')->where(fn (Builder $open) => $open
            ->where(fn (Builder $note) => $note
                ->whereNotNull('doctor_note_requested_at')
                ->whereNull('doctor_note_resolved_at'))
            ->orWhereHas('payments', fn ($payment) => $payment
                ->whereIn('status', PaymentStatus::openValues()))
            // A visit that has happened and was never marked paid. No payment row is
            // needed: the client-facing state calls this "pay cash on site".
            ->orWhere(fn (Builder $unpaidVisit) => $unpaidVisit
                ->whereDate('reservation_date', '<', today())
                ->where('status', '!=', ReservationStatus::Cancelled)
                ->whereIn('payment_status', PaymentStatus::openValues())));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function emailRecipientAddress(): ?string
    {
        return $this->client?->email;
    }

    public function emailRecipientName(): ?string
    {
        return $this->client?->name;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function emailTemplateGroups(): array
    {
        return ReservationEmailer::templateGroups($this);
    }

    public function sendTemplateEmail(EmailTemplateKey $key, ?CopyRecipients $copies = null): void
    {
        ReservationEmailer::send($this, $key, $copies);
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
        return $this->belongsTo(StaffProfile::class, 'therapist_id');
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

    /**
     * Files the client attached to this reservation (private disk).
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ReservationDocument::class);
    }

    /**
     * The doctor's notes backing a late cancellation. Their presence is what turns
     * the client-facing state from „čeká na potvrzení" into „potvrzení nahráno".
     */
    public function doctorNoteDocuments(): HasMany
    {
        return $this->documents()->where('type', ReservationDocumentType::DoctorNote);
    }

    /**
     * Whether the client promised a doctor's note that staff have not resolved yet
     * — the window in which uploading (and removing) a note is allowed.
     */
    public function awaitsDoctorNote(): bool
    {
        return $this->doctor_note_requested_at !== null && $this->doctor_note_resolved_at === null;
    }

    /**
     * Whether the client may still change how they resolve a late storno (promise a
     * doctor's note, pay the fee, or refuse). Open for as long as the storno is
     * unresolved — a client who cannot get the note after all must be able to switch
     * to paying. Deactivation is the one-way door: it blacklists the account, so
     * from there nothing can be changed online.
     */
    public function canChangeStornoResolution(): bool
    {
        return $this->status === ReservationStatus::Cancelled
            && $this->settled_at === null
            && ! (bool) $this->client?->isDeactivated()
            && ($this->awaitsDoctorNote() || $this->hasUnpaidStornoFee());
    }

    /**
     * A raised storno fee that has not been paid.
     */
    public function hasUnpaidStornoFee(): bool
    {
        return $this->payments()
            ->whereIn('status', PaymentStatus::openValues())
            ->exists();
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

    /**
     * Whether the reservation's obligation is closed given its final status: an
     * attended visit that is paid, or a storno whose fee was raised and paid. A
     * doctor-note waive sets {@see settled_at} directly (it clears no fee), so it
     * is intentionally not covered here.
     */
    public function qualifiesAsSettled(): bool
    {
        // Computed from the payments, not the cached payment_status column, so free
        // (price 0, no payment row) visits — whose cache is never refreshed — settle
        // too. A visit with nothing owed has paid (0) >= due (0).
        $paid = (int) $this->payments()->where('status', PaymentStatus::Paid->value)->sum('amount');

        if ($this->status === ReservationStatus::Confirmed) {
            return $this->startsAt()->isPast() && $paid >= $this->paymentAmountDue();
        }

        if ($this->status === ReservationStatus::Cancelled) {
            // A storno whose fee was raised and covered. (A waive sets settled_at directly.)
            return $this->payments()->exists() && $paid >= $this->paymentAmountDue();
        }

        return false;
    }

    /**
     * Stamps `settled_at` ("Vybaveno") once the obligation is closed. Monotonic —
     * it never clears the marker here (a payment reversal must not un-settle a
     * handled reservation); only an explicit reactivation resets it.
     */
    public function markSettledIfQualifies(): void
    {
        if ($this->settled_at === null && $this->qualifiesAsSettled()) {
            $this->update(['settled_at' => now()]);
            LogActivity::record('reservation_completed', $this, 'Rezervace vybavena');
        }
    }

    public function payableTherapist(): ?User
    {
        return $this->therapist?->user;
    }
}
