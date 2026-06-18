<?php

namespace App\Enums;

use Carbon\CarbonInterface;
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

    /**
     * The day-of-week case for the given date (e.g. a Monday date → DayOfWeek::Monday).
     */
    public static function fromCarbon(CarbonInterface $date): self
    {
        return self::from(strtolower($date->englishDayOfWeek));
    }

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
