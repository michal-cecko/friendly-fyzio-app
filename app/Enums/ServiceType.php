<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ServiceType: string implements HasColor, HasLabel
{
    case Physiotherapy = 'physiotherapy';
    case Massage = 'massage';

    public function getLabel(): string
    {
        return match ($this) {
            self::Physiotherapy => 'Fyzioterapie',
            self::Massage => 'Masáž',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Physiotherapy => 'info',
            self::Massage => 'success',
        };
    }
}
