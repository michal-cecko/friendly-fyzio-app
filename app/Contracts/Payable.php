<?php

namespace App\Contracts;

use App\Enums\PayableType;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A billable record that can carry Payment rows: reservations, course
 * enrollments, workshop registrations and one-time lesson bookings. Powers
 * payment recording, the auto-paid rule and invoice/receipt item generation.
 */
interface Payable
{
    public function payments(): MorphMany;

    /** The (at most one) invoice documenting this record once it is paid. */
    public function invoice(): MorphOne;

    /** The paying user. */
    public function client(): BelongsTo;

    public function payableType(): PayableType;

    /** Whole CZK owed for full settlement (storno/no-show fee when cancelled). */
    public function paymentAmountDue(): int;

    /**
     * Plain-text values for the item title/description template tokens.
     *
     * @return array<string, string>
     */
    public function payableTitleContext(): array;

    /**
     * Raw (settings-resolved) templates for the invoice item line.
     *
     * @return array{title: string, description: string}
     */
    public function invoiceItemTemplates(): array;

    public function hasPaidStatus(): bool;

    /** Idempotent: flip the payable's payment state to paid. */
    public function markPaymentPaid(): void;

    /** Recipient of therapist-facing payment e-mails, when resolvable. */
    public function payableTherapist(): ?User;
}
