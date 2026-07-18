<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CreditTransactionType: string implements HasColor, HasLabel
{
    case TopUp = 'top_up';
    case Deduction = 'deduction';
    case Expiration = 'expiration';

    public function getLabel(): string
    {
        return match ($this) {
            self::TopUp => 'Dobití',
            self::Deduction => 'Čerpání',
            self::Expiration => 'Propadnutí',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TopUp => 'success',
            self::Deduction => 'warning',
            self::Expiration => 'gray',
        };
    }
}
