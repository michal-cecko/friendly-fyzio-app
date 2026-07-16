<?php

namespace App\Console\Commands;

use App\Contracts\Payable;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Notifications\PaymentOverdueNotification;
use App\Notifications\TherapistPaymentOverdueNotification;
use Illuminate\Console\Command;

/**
 * Flips Unpaid payments past their due date to Overdue and sends the dunning
 * pair (client + therapist) exactly once per payment — overdue_notified_at is
 * the dedup guard, so a crashed run resumes where it stopped.
 */
class MarkOverduePayments extends Command
{
    protected $signature = 'payments:mark-overdue';

    protected $description = 'Označí platby po splatnosti a jednorázově odešle upozornění.';

    public function handle(): int
    {
        Payment::query()
            ->where('status', PaymentStatus::Unpaid->value)
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', today())
            ->update(['status' => PaymentStatus::Overdue->value]);

        $unnotified = Payment::query()
            ->where('status', PaymentStatus::Overdue->value)
            ->whereNull('overdue_notified_at')
            ->with(['client', 'payable'])
            ->get();

        foreach ($unnotified as $payment) {
            // The bulk status update above bypasses model events, so push the new
            // Overdue state onto the reservation cache here.
            if ($payment->payable instanceof Reservation) {
                $payment->payable->recalculatePaymentStatus();
            }

            if (filled($payment->client?->email)) {
                $payment->client->notify(new PaymentOverdueNotification($payment));
            }

            $therapist = $payment->payable instanceof Payable
                ? $payment->payable->payableTherapist()
                : null;

            if ($therapist !== null && filled($therapist->email)) {
                $therapist->notify(new TherapistPaymentOverdueNotification($payment));
            }

            $payment->forceFill(['overdue_notified_at' => now()])->save();
        }

        $this->info("Upozorněno na {$unnotified->count()} plateb po splatnosti.");

        return self::SUCCESS;
    }
}
