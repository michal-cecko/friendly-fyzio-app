<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReviewRequestChannel: string implements HasColor, HasLabel
{
    case Automatic = 'automatic';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Automatic => 'Automaticky',
            self::Manual => 'Ručně',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Automatic => 'gray',
            self::Manual => 'info',
        };
    }
}
