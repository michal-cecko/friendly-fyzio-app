<?php

namespace App\Enums;

use App\Support\Payments\PayablePaymentStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    /**
     * Withdrawn — the thing it was raised for is off (a cancelled sign-up, a
     * deactivated account). Kept on record rather than deleted, but it is no
     * longer a debt: it must never count as an open payment.
     */
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'Nezaplaceno',
            self::Paid => 'Zaplaceno',
            self::Overdue => 'Po splatnosti',
            self::Cancelled => 'Zrušeno',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'gray',
            self::Paid => 'success',
            self::Overdue => 'danger',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Whether money is still expected. The one predicate every "is this payment
     * still open?" check should use — a bare `!== Paid` would wrongly count a
     * withdrawn (Cancelled) payment as a debt.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Unpaid, self::Overdue], true);
    }

    /**
     * The open statuses as raw column values, for query builders.
     *
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return [self::Unpaid->value, self::Overdue->value];
    }

    /**
     * The statuses a payable's cached `payment_status` can hold — it is derived from
     * the payments ({@see PayablePaymentStatus}) and so never
     * takes {@see self::Cancelled}, which describes one payment record, not a debt.
     *
     * @return array<int, self>
     */
    public static function payableCases(): array
    {
        return [self::Unpaid, self::Paid, self::Overdue];
    }
}
