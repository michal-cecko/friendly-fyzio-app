<?php

namespace App\Support\Payments;

use App\Models\Payment;
use App\Support\Settings;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;

/**
 * Builds a Czech „QR Platba" (SPAYD) descriptor for a payment and renders it as a
 * PNG data-URI, embeddable directly in an e-mail. Hand-built SPAYD (CC:CZK, IBAN-based)
 * rendered with endroid/qr-code — no third-party QR-Platba dependency.
 */
class QrPlatba
{
    /**
     * The SPAYD 1.0 descriptor: `SPD*1.0*ACC:{IBAN}*AM:{amount}*CC:CZK*VS:{vs}*MSG:{msg}`.
     * Keys are uppercase and `*`-delimited; values may not contain `*`.
     */
    public static function spayd(Payment $payment): string
    {
        $segments = [
            'SPD',
            '1.0',
            'ACC:'.self::account(Settings::iban()),
            'AM:'.number_format((int) $payment->amount, 2, '.', ''),
            'CC:CZK',
            'VS:'.self::variableSymbol((string) $payment->variable_symbol),
        ];

        $message = self::message(PaymentNote::render($payment));

        if ($message !== '') {
            $segments[] = 'MSG:'.$message;
        }

        return implode('*', $segments);
    }

    /**
     * The SPAYD descriptor rendered as a PNG data-URI (e.g. „data:image/png;base64,…").
     */
    public static function dataUri(Payment $payment): string
    {
        return Builder::create()
            ->writer(new PngWriter)
            ->data(self::spayd($payment))
            ->size(240)
            ->margin(8)
            ->build()
            ->getDataUri();
    }

    /**
     * Normalise the IBAN: strip spaces and uppercase (no `*`, safe for the ACC field).
     */
    private static function account(string $iban): string
    {
        return Str::of($iban)->replace(' ', '')->upper()->toString();
    }

    /**
     * Keep only digits and cap at the SPAYD 10-character variable-symbol limit.
     */
    private static function variableSymbol(string $vs): string
    {
        return substr(preg_replace('/\D/', '', $vs) ?? '', 0, 10);
    }

    /**
     * ASCII-transliterate the message, drop the reserved `*`/`%`, and cap at 60 chars.
     */
    private static function message(string $message): string
    {
        $ascii = Str::ascii($message);
        $ascii = str_replace(['*', '%'], '', $ascii);

        return trim(mb_substr($ascii, 0, 60));
    }
}
