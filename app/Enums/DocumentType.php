<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasLabel
{
    case Invoice = 'invoice';
    case Receipt = 'receipt';

    public function getLabel(): string
    {
        return match ($this) {
            self::Invoice => 'Faktura',
            self::Receipt => 'Pokladní doklad',
        };
    }
}
