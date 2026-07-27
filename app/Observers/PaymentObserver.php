<?php

namespace App\Observers;

use App\Contracts\Payable;
use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\Enrollments\CloseOverInvitedLosers;
use App\Support\Invoices\CashReceiptGenerator;
use App\Support\Invoices\DocumentNumberAllocator;
use App\Support\Payments\PayableSettlement;
use Illuminate\Support\Facades\DB;

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

        $this->syncPayable($payment);
        $this->syncInvoice($payment);
    }

    /**
     * A corrected amount decides whether the payable is covered just as much as
     * a status change does, so editing one down (or up) has to recompute the
     * cached payment status too — only becoming Paid additionally issues the PPD.
     */
    public function updated(Payment $payment): void
    {
        if (! $payment->wasChanged(['status', 'amount'])) {
            return;
        }

        if ($payment->wasChanged('status') && $payment->status === PaymentStatus::Paid) {
            $this->handlePaid($payment);
        }

        $this->syncPayable($payment);
        $this->syncInvoice($payment);
    }

    public function deleted(Payment $payment): void
    {
        $this->syncPayable($payment);
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
     * A reservation's and a course enrollment's payment_status (+ the
     * enrollment's paid_at) are a deterministic cache of their payments, so every
     * payment change (created / status change / deleted) recomputes them —
     * including reverting to Unpaid when a covering payment is removed. The
     * remaining payables keep the forward-only PayableSettlement rule in
     * handlePaid().
     */
    private function syncPayable(Payment $payment): void
    {
        $payable = $payment->payable;

        if ($payable instanceof Reservation) {
            $payable->recalculatePaymentStatus();
            // A past visit now fully paid — or a storno fee just settled — is closed.
            $payable->markSettledIfQualifies();

            return;
        }

        if ($payable instanceof CourseEnrollment) {
            $payable->recalculatePaymentStatus();
        }
    }

    private function handlePaid(Payment $payment): void
    {
        PayableSettlement::settle($payment);

        // If this payment just filled a course/event that was over-invited from
        // the waitlist ("kdo dřív zaplatí"), close out the slower unpaid holders.
        // Deferred past commit so the cancel → waitlist-promote cascade never
        // nests inside the settlement transaction.
        DB::afterCommit(fn () => app(CloseOverInvitedLosers::class)->afterPayment($payment));

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
