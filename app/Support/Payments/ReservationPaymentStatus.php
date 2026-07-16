<?php

namespace App\Support\Payments;

use App\Enums\PaymentStatus;
use App\Models\Reservation;

/**
 * Derives a reservation's payment status purely from its payment records:
 *
 *  - Paid    — received (Paid) payments cover the amount due;
 *  - Overdue — not covered and an open payment has passed its due date;
 *  - Unpaid  — otherwise.
 *
 * The stored `reservations.payment_status` column is only ever a cache of this
 * value (kept fresh by the PaymentObserver and the overdue command); it is never
 * set by hand in the form.
 */
final class ReservationPaymentStatus
{
    public static function for(Reservation $reservation): PaymentStatus
    {
        $due = $reservation->paymentAmountDue();

        $paid = (int) $reservation->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');

        if ($due > 0 && $paid >= $due) {
            return PaymentStatus::Paid;
        }

        $overdue = $reservation->payments()
            ->whereIn('status', [PaymentStatus::Unpaid->value, PaymentStatus::Overdue->value])
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', today())
            ->exists();

        return $overdue ? PaymentStatus::Overdue : PaymentStatus::Unpaid;
    }
}
