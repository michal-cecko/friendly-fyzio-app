<?php

namespace App\Enums;

use App\Support\Reservations\ReservationSlots;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How much a detected overlap actually matters.
 *
 * `Hard` is something nobody intended and the booking system cannot resolve on
 * its own — two bookings in one room, a therapist expected in two places.
 *
 * `Soft` is an overlap that is structurally normal and already handled: a room
 * blocking sitting inside a therapist's working hours is subtracted by
 * {@see ReservationSlots} before a client ever sees a
 * slot, so it is listed for the overview, never counted as a fault.
 */
enum ConflictSeverity: string implements HasColor, HasLabel
{
    case Hard = 'hard';
    case Soft = 'soft';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hard => 'Konflikt',
            self::Soft => 'Očekávaný překryv',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hard => 'danger',
            self::Soft => 'warning',
        };
    }
}
