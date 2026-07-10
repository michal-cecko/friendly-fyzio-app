<?php

namespace App\Support\Payments;

use App\Models\Payment;
use App\Models\Reservation;
use App\Support\Reservations\ReservationEmailContext;
use App\Support\Settings;

/**
 * Resolves the editable QR/payment note (the `payments.qr_message` setting) for a
 * specific payment, substituting {{ tokens }} from the payable (a reservation) and
 * the payment itself. The result is plain text: it feeds the QR-Platba message field
 * and the payment e-mail.
 */
class PaymentNote
{
    public static function render(Payment $payment): string
    {
        return self::substitute(Settings::qrMessage(), self::context($payment));
    }

    /**
     * Tokens available in the note: the payable's reservation tokens
     * (jmeno/sluzba/terapeut/termin/…) plus the payment's `vs` and `castka`.
     *
     * @return array<string, string>
     */
    public static function context(Payment $payment): array
    {
        $payable = $payment->payable;

        $base = $payable instanceof Reservation
            ? ReservationEmailContext::for($payable)
            : [];

        return [
            ...$base,
            'vs' => (string) $payment->variable_symbol,
            'castka' => number_format((int) $payment->amount, 0, ',', ' '),
        ];
    }

    /**
     * Replace every {{ token }} with its context value. Plain text (no HTML escaping);
     * unknown tokens resolve to an empty string.
     *
     * @param  array<string, string>  $context
     */
    private static function substitute(string $template, array $context): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $matches): string => $context[$matches[1]] ?? '',
            $template,
        ) ?? $template;
    }
}
