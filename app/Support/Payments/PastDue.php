<?php

namespace App\Support\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Money that was due before today and never arrived.
 *
 * Deliberately derived from `due_at` rather than from the Overdue status:
 * `payments:mark-overdue` and `invoices:mark-overdue` are part of the schedule,
 * which is switched off before launch, so the status alone would report an
 * empty backlog while the real one grows.
 *
 * Shared by the Návrhy rules and the "Po splatnosti" table filters, so the card
 * and the list it links to always describe the same set.
 */
final class PastDue
{
    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public static function payments(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [PaymentStatus::Unpaid, PaymentStatus::Overdue])
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', today());
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public static function invoices(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [InvoiceStatus::New, InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', today());
    }
}
