<?php

namespace App\Observers;

use App\Contracts\Payable;
use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\Invoices\CashReceiptGenerator;
use App\Support\Invoices\DocumentNumberAllocator;
use App\Support\Payments\PayableSettlement;

/**
 * Payment automation: any payment that becomes Paid settles its payable when the
 * amount due is covered, and a received CASH payment automatically gets its
 * příjmový pokladní doklad. Notifications are deliberately NOT sent here — the
 * triggering actions own them (they carry the "notify client" toggle).
 */
class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $this->linkPayableInvoice($payment);

        if ($payment->status === PaymentStatus::Paid) {
            $this->handlePaid($payment);
        }

        $this->syncReservation($payment);
        $this->syncInvoice($payment);
    }

    public function updated(Payment $payment): void
    {
        if (! $payment->wasChanged('status')) {
            return;
        }

        if ($payment->status === PaymentStatus::Paid) {
            $this->handlePaid($payment);
        }

        $this->syncReservation($payment);
        $this->syncInvoice($payment);
    }

    public function deleted(Payment $payment): void
    {
        $this->syncReservation($payment);
        // Invoices deliberately NOT synced here: their paid derivation is
        // forward-only — removing a payment never reverts an issued document.
    }

    /**
     * A payment recorded on a payable that already has its invoice joins the
     * invoice's thread (the VS matched at creation via the debt inheritance).
     */
    private function linkPayableInvoice(Payment $payment): void
    {
        if ($payment->invoice_id !== null || ! $payment->payable instanceof Payable) {
            return;
        }

        $invoiceId = $payment->payable->invoice()->value('id');

        if ($invoiceId !== null) {
            $payment->forceFill(['invoice_id' => $invoiceId])->saveQuietly();
        }
    }

    private function syncInvoice(Payment $payment): void
    {
        $payment->invoice()->first()?->refreshPaidStatus();
    }

    /**
     * A reservation's payment_status is a deterministic cache of its payments, so
     * every payment change (created / status change / deleted) recomputes it —
     * including reverting to Unpaid when a covering payment is removed. Other
     * payables keep the forward-only PayableSettlement rule in handlePaid().
     */
    private function syncReservation(Payment $payment): void
    {
        if ($payment->payable instanceof Reservation) {
            $payment->payable->recalculatePaymentStatus();
        }
    }

    private function handlePaid(Payment $payment): void
    {
        PayableSettlement::settle($payment);

        if (
            $payment->method === PaymentMethod::Cash
            && ! $payment->cashReceipt()->exists()
            // Skip quietly when no receipt series is configured yet (fresh install);
            // the manual "Vystavit příjmový doklad" action stays available.
            && app(DocumentNumberAllocator::class)->defaultSeries(DocumentType::Receipt) !== null
        ) {
            app(CashReceiptGenerator::class)->fromPayment($payment);
        }
    }
}
