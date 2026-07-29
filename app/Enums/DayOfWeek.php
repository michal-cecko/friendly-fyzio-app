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

    /**
     * The label as it reads mid-sentence — "úterý a čtvrtek 17:30". The public
     * site writes weekdays lowercase (Carbon's translatedFormat('l') does the
     * same), while getLabel() stays capitalized for Filament's pickers.
     */
    public function lowerLabel(): string
    {
        return mb_strtolower($this->getLabel());
    }

    /**
     * Position in the week, Monday = 0 — the sort order for a série's rozvrh.
     */
    public function order(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
