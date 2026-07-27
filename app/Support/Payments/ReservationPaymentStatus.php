<?php

namespace App\Support\Payments;

use App\Enums\PaymentStatus;
use App\Models\Reservation;

/**
 * Derives a reservation's payment status purely from its payment records. Thin
 * wrapper over the general {@see PayablePaymentStatus} kept for its expressive
 * call sites (the overdue command, the reservation observer sync).
 *
 * The stored `reservations.payment_status` column is only ever a cache of this
 * value (kept fresh by the PaymentObserver and the overdue command); it is never
 * set by hand in the form.
 */
final class ReservationPaymentStatus
{
    public static function for(Reservation $reservation): PaymentStatus
    {
        return PayablePaymentStatus::for($reservation);
    }
}
