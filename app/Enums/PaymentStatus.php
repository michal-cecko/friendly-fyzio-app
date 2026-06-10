<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'Nezaplaceno',
            self::Paid => 'Zaplaceno',
            self::Overdue => 'Po splatnosti',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'gray',
            self::Paid => 'success',
            self::Overdue => 'danger',
        };
    }
}
