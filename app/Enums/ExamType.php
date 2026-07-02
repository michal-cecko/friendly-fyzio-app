<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Distinguishes a physiotherapy service for new patients (an input examination,
 * bookable by anyone) from a follow-up for existing patients (a control therapy,
 * which requires logging in and a recent prior visit). Null on non-physiotherapy
 * services (e.g. massages).
 */
enum ExamType: string implements HasColor, HasLabel
{
    case Vstupni = 'vstupni';
    case Kontrolni = 'kontrolni';

    public function getLabel(): string
    {
        return match ($this) {
            self::Vstupni => 'Vstupní vyšetření',
            self::Kontrolni => 'Kontrolní vyšetření',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Vstupni => 'success',
            self::Kontrolni => 'info',
        };
    }
}
