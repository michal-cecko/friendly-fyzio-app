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
 */
enum ClientReservationState: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case AwaitingQr = 'awaiting_qr';
    case AwaitingCash = 'awaiting_cash';
    case AwaitingCredit = 'awaiting_credit';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public static function for(Reservation $reservation): self
    {
        if ($reservation->status === ReservationStatus::Cancelled) {
            return self::Cancelled;
        }

        if ($reservation->startsAt()->isFuture()) {
            return $reservation->status === ReservationStatus::Pending ? self::Pending : self::Confirmed;
        }

        if ($reservation->payment_status === PaymentStatus::Paid) {
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
            ->filter(fn (Payment $payment): bool => $payment->status !== PaymentStatus::Paid)
            ->sortByDesc('created_at')
            ->first()
            ?->method;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Čeká na potvrzení',
            self::Confirmed => 'Potvrzeno',
            self::AwaitingQr => 'Čeká na platbu',
            self::AwaitingCash => 'Čeká na platbu (hotově)',
            self::AwaitingCredit => 'Čeká na platbu (kredit)',
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
            self::Completed => 'bg-neutral-100 text-neutral-600',
            self::Cancelled => 'bg-red-50 text-red-600',
        };
    }

    public function isAwaitingPayment(): bool
    {
        return in_array($this, [self::AwaitingQr, self::AwaitingCash, self::AwaitingCredit], true);
    }
}
