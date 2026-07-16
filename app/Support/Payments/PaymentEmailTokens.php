<?php

namespace App\Support\Payments;

use App\Models\Payment;
use App\Support\Settings;

/**
 * Builds the shared {{ token }} set for the payment/storno reservation e-mails
 * (castka / iban / vs / qr / splatnost) from a Payment. Reused by every action that
 * mails a QR-Platba request so the tokens stay consistent.
 */
class PaymentEmailTokens
{
    /**
     * @return array<string, string>
     */
    public static function for(Payment $payment): array
    {
        return [
            'castka' => number_format((int) $payment->amount, 0, ',', ' '),
            'iban' => Settings::iban(),
            'vs' => (string) $payment->variable_symbol,
            'qr' => QrPlatba::dataUri($payment),
            'splatnost' => $payment->due_at?->format('d. m. Y') ?? '',
        ];
    }
}
