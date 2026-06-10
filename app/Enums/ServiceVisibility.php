<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ServiceVisibility: string implements HasColor, HasLabel
{
    case Public = 'public';
    case Clients = 'clients';
    case Hidden = 'hidden';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Veřejná',
            self::Clients => 'Pro klienty',
            self::Hidden => 'Skrytá',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Clients => 'info',
            self::Hidden => 'gray',
        };
    }
}
