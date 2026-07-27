<?php

namespace App\Support\Payments;

use App\Contracts\Payable;
use App\Enums\PaymentStatus;
use App\Observers\PaymentObserver;

/**
 * Derives a payable's payment status purely from its payment records:
 *
 *  - Paid    — received (Paid) payments cover the amount due;
 *  - Overdue — not covered and an open payment has passed its due date;
 *  - Unpaid  — otherwise.
 *
 * The stored `payment_status` column is only ever a cache of this value; it is
 * kept fresh by the {@see PaymentObserver} on every payment
 * change and is never set by hand.
 */
final class PayablePaymentStatus
{
    public static function for(Payable $payable): PaymentStatus
    {
        $due = $payable->paymentAmountDue();

        $paid = (int) $payable->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');

        if ($due > 0 && $paid >= $due) {
            return PaymentStatus::Paid;
        }

        $overdue = $payable->payments()
            ->whereIn('status', [PaymentStatus::Unpaid->value, PaymentStatus::Overdue->value])
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', today())
            ->exists();

        return $overdue ? PaymentStatus::Overdue : PaymentStatus::Unpaid;
    }
}
