<?php

namespace App\Support\Invoices;

use NumberFormatter;

/**
 * Whole-CZK amount spelled out in Czech for the PPD "slovy:" line,
 * e.g. 1300 → "jedna tisíc tři sta korun českých".
 */
final class CzechAmountInWords
{
    public static function for(int $czk): string
    {
        $formatter = new NumberFormatter('cs', NumberFormatter::SPELLOUT);

        $words = (string) $formatter->format($czk);

        return trim($words.' '.self::currencyWord($czk));
    }

    private static function currencyWord(int $czk): string
    {
        return match (true) {
            $czk === 1 => 'koruna česká',
            $czk >= 2 && $czk <= 4 => 'koruny české',
            default => 'korun českých',
        };
    }
}
