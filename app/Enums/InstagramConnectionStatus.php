<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Lifecycle of an Instagram connection: freshly created and awaiting OAuth
 * authorization, successfully connected with a valid token, or in an error
 * state (expired/revoked token or a failed sync).
 */
enum InstagramConnectionStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Connected = 'connected';
    case Error = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Čeká na autorizaci',
            self::Connected => 'Připojeno',
            self::Error => 'Chyba',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Connected => 'success',
            self::Error => 'danger',
        };
    }
}
