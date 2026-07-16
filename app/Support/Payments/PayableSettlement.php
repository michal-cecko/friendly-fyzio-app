<?php

namespace App\Support\Payments;

use App\Contracts\Payable;
use App\Enums\PaymentStatus;
use App\Models\Payment;

/**
 * The auto-paid rule: once a payable's received (Paid) payments cover its amount
 * due, the payable itself flips to paid. Forward-only automation — deleting or
 * un-marking a payment never reverts the payable; that stays a manual correction.
 */
final class PayableSettlement
{
    public static function settle(Payment $payment): void
    {
        $payable = $payment->payable;

        if (! $payable instanceof Payable || $payable->hasPaidStatus()) {
            return;
        }

        $paid = (int) $payable->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');

        if ($paid >= $payable->paymentAmountDue()) {
            $payable->markPaymentPaid();
        }
    }
}
