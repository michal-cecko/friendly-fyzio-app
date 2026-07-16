<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Qr = 'qr';
    case Cash = 'cash';
    case Credit = 'credit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Qr => 'QR platba',
            self::Cash => 'Hotovost',
            self::Credit => 'Kredit',
        };
    }
}
