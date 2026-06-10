<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CourseEnrollmentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Waitlist = 'waitlist';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Aktivní',
            self::Cancelled => 'Zrušeno',
            self::Waitlist => 'Náhradník',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Cancelled => 'danger',
            self::Waitlist => 'warning',
        };
    }
}
