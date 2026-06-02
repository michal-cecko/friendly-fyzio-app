<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case Therapist = 'therapist';
    case Customer = 'customer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrátor',
            self::Therapist => 'Terapeut',
            self::Customer => 'Klient',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Therapist => 'info',
            self::Customer => 'gray',
        };
    }

    /**
     * The Shield/Spatie role automatically assigned for this account type,
     * or null when the account type has no panel role (customers).
     */
    public function shieldRole(): ?string
    {
        return match ($this) {
            self::Admin => 'super_admin',
            self::Therapist => 'therapist',
            self::Customer => null,
        };
    }
}
