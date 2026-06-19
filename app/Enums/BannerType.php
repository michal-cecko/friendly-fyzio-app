<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BannerType: string implements HasColor, HasLabel
{
    case Topbar = 'topbar';
    case Floating = 'floating';
    case Popup = 'popup';

    public function getLabel(): string
    {
        return match ($this) {
            self::Topbar => 'Horní lišta',
            self::Floating => 'Plovoucí okno',
            self::Popup => 'Vyskakovací okno',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Topbar => 'info',
            self::Floating => 'warning',
            self::Popup => 'danger',
        };
    }
}
