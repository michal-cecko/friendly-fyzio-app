<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

enum WeekType: string implements HasLabel
{
    case All = 'all';
    case Odd = 'odd';
    case Even = 'even';

    /**
     * The concrete parity (Odd/Even) of the week containing the given date,
     * by ISO-8601 week number. Recurring rows whose week_type is All match every
     * week; rows set to Odd/Even match only weeks whose number shares that parity.
     */
    public static function forDate(CarbonInterface $date): self
    {
        return $date->isoWeek() % 2 === 0 ? self::Even : self::Odd;
    }

    /**
     * Whether a recurring row carrying this week_type occurs on the given date.
     */
    public function matchesDate(CarbonInterface $date): bool
    {
        return $this === self::All || $this === self::forDate($date);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'Každý týden',
            self::Odd => 'Lichý týden',
            self::Even => 'Sudý týden',
        };
    }
}
