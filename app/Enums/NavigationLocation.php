<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationLocation: string implements HasLabel
{
    case Header = 'header';
    case Footer = 'footer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Header => 'Hlavní menu (hlavička)',
            self::Footer => 'Patička',
        };
    }
}
