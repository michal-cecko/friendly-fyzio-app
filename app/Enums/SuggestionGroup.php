<?php

namespace App\Enums;

/**
 * The domains the Návrhy page groups its cards by. Only a presentation
 * grouping — a rule's group says where its card is listed, never what it does.
 */
enum SuggestionGroup: string
{
    case Kurzy = 'kurzy';
    case Rezervace = 'rezervace';
    case Platby = 'platby';
    case Obsah = 'obsah';

    public function label(): string
    {
        return match ($this) {
            self::Kurzy => 'Kurzy a lekce',
            self::Rezervace => 'Rezervace',
            self::Platby => 'Platby a faktury',
            self::Obsah => 'Obsah',
        };
    }
}
