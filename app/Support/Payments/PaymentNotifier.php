<?php

namespace App\Support\Payments;

use App\Contracts\Payable;
use App\Models\Payment;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\TherapistPaymentReceivedNotification;

/**
 * Explicit payment-received notifications. Deliberately NOT observer-driven:
 * the triggering actions call this so the admin's "notify client" toggle is
 * always honored and no e-mail is ever sent as a hidden side effect.
 */
final class PaymentNotifier
{
    public static function paymentReceived(Payment $payment, bool $notifyClient = true): void
    {
        if ($notifyClient && filled($payment->client?->email)) {
            $payment->client->notify(new PaymentReceivedNotification($payment));
        }

        $therapist = $payment->payable instanceof Payable
            ? $payment->payable->payableTherapist()
            : null;

        if ($therapist !== null && filled($therapist->email)) {
            $therapist->notify(new TherapistPaymentReceivedNotification($payment));
        }
    }
}
