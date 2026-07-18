<?php

namespace App\Support\Payments;

use App\Contracts\Payable;
use App\Models\Payment;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\TherapistPaymentReceivedNotification;
use App\Support\ActivityLog\LogActivity;

/**
 * Explicit payment-received notifications. Deliberately NOT observer-driven:
 * the triggering actions call this so the admin's "notify client" toggle is
 * always honored and no e-mail is ever sent as a hidden side effect.
 */
final class PaymentNotifier
{
    public static function paymentReceived(Payment $payment, bool $notifyClient = true): void
    {
        $notifiedClient = $notifyClient && filled($payment->client?->email);

        if ($notifiedClient) {
            $payment->client->notify(new PaymentReceivedNotification($payment));
        }

        $therapist = $payment->payable instanceof Payable
            ? $payment->payable->payableTherapist()
            : null;

        $notifiedTherapist = $therapist !== null && filled($therapist->email);

        if ($notifiedTherapist) {
            $therapist->notify(new TherapistPaymentReceivedNotification($payment));
        }

        LogActivity::record('payment_received', $payment, 'Platba přijata', [
            'amount' => $payment->amount.' Kč',
            'notified_client' => $notifiedClient,
            'notified_therapist' => $notifiedTherapist,
        ]);
    }
}
