<?php

namespace App\Mason\Support;

/**
 * Curated set of Heroicons (outline) offered in brick icon pickers. Using a
 * fixed list guarantees every stored name resolves via the blade-icons svg()
 * helper on the frontend.
 */
class Icons
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'heroicon-o-heart' => 'Srdce',
            'heroicon-o-sparkles' => 'Jiskry',
            'heroicon-o-hand-raised' => 'Dlaň',
            'heroicon-o-bolt' => 'Blesk',
            'heroicon-o-user-group' => 'Skupina',
            'heroicon-o-academic-cap' => 'Vzdělání',
            'heroicon-o-sun' => 'Slunce',
            'heroicon-o-fire' => 'Oheň',
            'heroicon-o-shield-check' => 'Štít',
            'heroicon-o-star' => 'Hvězda',
            'heroicon-o-clock' => 'Hodiny',
            'heroicon-o-map-pin' => 'Lokace',
            'heroicon-o-phone' => 'Telefon',
            'heroicon-o-chat-bubble-left-right' => 'Konverzace',
            'heroicon-o-check-circle' => 'Potvrzení',
            'heroicon-o-beaker' => 'Terapie',
        ];
    }
}
