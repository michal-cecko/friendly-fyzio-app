<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CourseSeriesStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Full = 'full';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Otevřený',
            self::Full => 'Plný',
            self::Inactive => 'Neaktivní',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Full => 'warning',
            self::Inactive => 'gray',
        };
    }
}
