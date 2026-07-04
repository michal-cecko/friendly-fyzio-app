<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContactInquiryStatus: string implements HasColor, HasLabel
{
    case New = 'novy';
    case InProgress = 'resi_se';
    case Handled = 'vyrizeno';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nový',
            self::InProgress => 'Řeší se',
            self::Handled => 'Vyřízeno',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::Handled => 'success',
        };
    }
}
