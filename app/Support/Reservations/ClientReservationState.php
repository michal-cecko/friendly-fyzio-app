<?php

namespace App\Support\Reservations;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;

/**
 * The customer-facing display state of a reservation — richer than the stored
 * ReservationStatus. Reservations are normally paid on site after the visit,
 * so the "awaiting payment" variants describe a finished (or storno-cancelled)
 * visit whose payment is still open, split by how it is to be paid: QR request,
 * cash at the reception (the default when no payment row exists), or credit.
 *
 * A cancellation is not automatically the end of the story: a late storno may
 * still owe a fee, or wait on the doctor's note that waives it. Those get their
 * own states so „Stornováno" never hides an open obligation from the client.
 */
enum ClientReservationState: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case AwaitingQr = 'awaiting_qr';
    case AwaitingCash = 'awaiting_cash';
    case AwaitingCredit = 'awaiting_credit';
    case AwaitingDoctorNote = 'awaiting_doctor_note';
    case DoctorNoteSubmitted = 'doctor_note_submitted';
    case CancelledUnpaid = 'cancelled_unpaid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public static function for(Reservation $reservation): self
    {
        if ($reservation->status === ReservationStatus::Cancelled) {
            if ($reservation->awaitsDoctorNote()) {
                return $reservation->doctorNoteDocuments->isNotEmpty()
                    ? self::DoctorNoteSubmitted
                    : self::AwaitingDoctorNote;
            }

            return self::hasOpenPayment($reservation) ? self::CancelledUnpaid : self::Cancelled;
        }

        if ($reservation->startsAt()->isFuture()) {
            return $reservation->status === ReservationStatus::Pending ? self::Pending : self::Confirmed;
        }

        // "Vybaveno" is the canonical handled marker — a settled visit is done even
        // when its cached payment_status was never refreshed (e.g. nothing was owed).
        if ($reservation->settled_at !== null || $reservation->payment_status === PaymentStatus::Paid) {
            return self::Completed;
        }

        return match (self::openPaymentMethod($reservation)) {
            PaymentMethod::Qr => self::AwaitingQr,
            PaymentMethod::Credit => self::AwaitingCredit,
            default => self::AwaitingCash,
        };
    }

    /**
     * Method of the newest still-open payment request; pay-on-site (cash) when
     * the reservation has no payment row at all.
     */
    protected static function openPaymentMethod(Reservation $reservation): ?PaymentMethod
    {
        return $reservation->payments
            ->filter(fn (Payment $payment): bool => $payment->status->isOpen())
            ->sortByDesc('created_at')
            ->first()
            ?->method;
    }

    /**
     * Whether a real, still-unpaid payment row exists. Unlike the "awaiting" states
     * of a finished visit, a cancellation only counts as unpaid when a fee was
     * actually raised — there is nothing to pay on site for a visit that never was.
     */
    protected static function hasOpenPayment(Reservation $reservation): bool
    {
        return $reservation->payments
            ->contains(fn (Payment $payment): bool => $payment->status->isOpen());
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Čeká na potvrzení',
            self::Confirmed => 'Potvrzeno',
            self::AwaitingQr => 'Čeká na platbu',
            self::AwaitingCash => 'Čeká na platbu (hotově)',
            self::AwaitingCredit => 'Čeká na platbu (kredit)',
            self::AwaitingDoctorNote => 'Čeká na potvrzení od lékaře',
            self::DoctorNoteSubmitted => 'Potvrzení nahráno – čeká na schválení',
            self::CancelledUnpaid => 'Stornováno – čeká na úhradu',
            self::Completed => 'Dokončeno',
            self::Cancelled => 'Stornováno',
        };
    }

    /**
     * Badge styling classes used by the zone views (light background + text).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Confirmed => 'bg-emerald-50 text-emerald-700',
            self::Pending => 'bg-amber-50 text-amber-700',
            self::AwaitingQr, self::AwaitingCash, self::AwaitingCredit => 'bg-amber-50 text-amber-700',
            self::AwaitingDoctorNote, self::CancelledUnpaid => 'bg-amber-50 text-amber-700',
            self::DoctorNoteSubmitted => 'bg-sky-50 text-sky-700',
            self::Completed => 'bg-neutral-100 text-neutral-600',
            self::Cancelled => 'bg-red-50 text-red-600',
        };
    }

    /**
     * There is money outstanding — drives the "Zaplatit" button and the QR panel.
     * Includes an unpaid storno fee: the visit is off, but the fee is not.
     */
    public function isAwaitingPayment(): bool
    {
        return in_array($this, [self::AwaitingQr, self::AwaitingCash, self::AwaitingCredit, self::CancelledUnpaid], true);
    }

    /**
     * Whether the client still has something open — money owed or a doctor's note
     * to deliver. Keeps the reservation in the zone's „Aktivní" tab, highlighted,
     * until staff close it out.
     */
    public function needsAttention(): bool
    {
        return $this->isAwaitingPayment()
            || in_array($this, [self::AwaitingDoctorNote, self::DoctorNoteSubmitted], true);
    }

    /**
     * A late cancellation whose storno is still being resolved.
     */
    public function isDoctorNotePending(): bool
    {
        return in_array($this, [self::AwaitingDoctorNote, self::DoctorNoteSubmitted], true);
    }
}
