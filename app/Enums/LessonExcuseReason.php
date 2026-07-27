<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Why a client is not on a lesson. Recorded by staff when they take somebody off
 * the presence list, and kept internal — it never reaches the client.
 *
 * {@see self::NoShow} is the honest option for an absence noticed only after the
 * lesson: nobody excused themselves, they simply did not turn up.
 */
enum LessonExcuseReason: string implements HasColor, HasLabel
{
    case Illness = 'illness';
    case Vacation = 'vacation';
    case Work = 'work';
    case Family = 'family';
    case Injury = 'injury';
    case NoShow = 'no_show';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Illness => 'Nemoc',
            self::Vacation => 'Dovolená / cestování',
            self::Work => 'Pracovní důvody',
            self::Family => 'Rodinné důvody',
            self::Injury => 'Zranění',
            self::NoShow => 'Nedorazil(a) bez omluvy',
            self::Other => 'Jiné',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NoShow => 'danger',
            self::Other => 'gray',
            default => 'warning',
        };
    }
}
