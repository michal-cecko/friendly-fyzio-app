<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PageStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Koncept',
            self::Published => 'Publikováno',
            self::Archived => 'Archivováno',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Archived => 'warning',
        };
    }
}
