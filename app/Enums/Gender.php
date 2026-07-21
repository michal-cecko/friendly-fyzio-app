<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Gender: string implements HasColor, HasIcon, HasLabel
{
    case Female = 'female';
    case Male = 'male';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Female => 'Žena',
            self::Male => 'Muž',
            self::Other => 'Jiné',
        };
    }

    /**
     * @return array<int|string, string>|string
     */
    public function getColor(): array|string
    {
        return match ($this) {
            self::Female => Color::Pink,
            self::Male => Color::Blue,
            self::Other => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Female => 'lucide-venus',
            self::Male => 'lucide-mars',
            self::Other => 'lucide-venus-and-mars',
        };
    }
}
