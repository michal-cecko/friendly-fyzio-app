<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WeekType: string implements HasLabel
{
    case All = 'all';
    case Odd = 'odd';
    case Even = 'even';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'Každý týden',
            self::Odd => 'Lichý týden',
            self::Even => 'Sudý týden',
        };
    }
}
