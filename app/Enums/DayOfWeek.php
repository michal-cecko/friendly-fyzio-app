<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DayOfWeek: string implements HasLabel
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monday => 'Pondělí',
            self::Tuesday => 'Úterý',
            self::Wednesday => 'Středa',
            self::Thursday => 'Čtvrtek',
            self::Friday => 'Pátek',
            self::Saturday => 'Sobota',
            self::Sunday => 'Neděle',
        };
    }
}
