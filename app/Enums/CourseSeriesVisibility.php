<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CourseSeriesVisibility: string implements HasColor, HasLabel
{
    case Public = 'public';
    case Private = 'private';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Veřejný',
            self::Private => 'Soukromý',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Private => 'gray',
        };
    }
}
