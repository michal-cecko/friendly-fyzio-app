<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReservationStatus: string implements HasColor, HasLabel
{
    case Confirmed = 'confirmed';
    case Pending = 'pending';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Confirmed => 'Potvrzeno',
            self::Pending => 'Čeká na potvrzení',
            self::Cancelled => 'Zrušeno',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Pending => 'warning',
            self::Cancelled => 'danger',
        };
    }
}
