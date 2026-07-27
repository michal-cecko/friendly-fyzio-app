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

    public function description(): string
    {
        return match ($this) {
            self::Public => 'Série se běžně nabízí na webu — ve výpisu kurzů i na stránce kurzu. Přihlašovací odkaz se u ní proto nenabízí, není k čemu.',
            self::Private => 'Série se na webu nikde neukáže; přihlásit se dá jen přes přihlašovací odkaz nebo pozvánku, které najdete v akcích na detailu série.',
        };
    }

    /**
     * Keyed by case value for a Radio / ToggleButtons `->descriptions()` call.
     *
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $visibility): array => $carry + [$visibility->value => $visibility->description()],
            [],
        );
    }
}
