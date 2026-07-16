<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Who confirmed a reservation: automatically (last-minute booking), by the customer
 * (magic link), or by staff on the customer's behalf (the therapist/admin). The
 * confirming person is stored separately in `confirmed_by_id`.
 */
enum ConfirmationSource: string implements HasColor, HasLabel
{
    case Automatic = 'automatic';
    case Customer = 'customer';
    case Therapist = 'therapist';

    public function getLabel(): string
    {
        return match ($this) {
            self::Automatic => 'Automaticky',
            self::Customer => 'Zákazník',
            self::Therapist => 'Terapeut',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Automatic => 'gray',
            self::Customer => 'success',
            self::Therapist => 'info',
        };
    }
}
