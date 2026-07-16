<?php

namespace App\Support;

use App\Enums\SettingValueType;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed accessor for application settings.
 *
 * Only primitive data is cached (key => [type, value]) — never Eloquent models —
 * so the cache stays serialization-safe across stores and Octane workers. The cache
 * is invalidated by the Setting model's saved/deleted hooks.
 */
class Settings
{
    public const CACHE_KEY = 'app.settings';

    /**
     * Raw settings keyed by `key`, each as ['type' => string, 'value' => ?string].
     *
     * @return array<string, array{type: string, value: ?string}>
     */
    public static function raw(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()
                ->get(['key', 'type', 'value'])
                ->mapWithKeys(fn (Setting $setting): array => [
                    $setting->key => ['type' => $setting->type->value, 'value' => $setting->value],
                ])
                ->all()
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = self::raw()[$key] ?? null;

        if ($setting === null) {
            return $default;
        }

        return SettingValueType::from($setting['type'])->cast($setting['value']) ?? $default;
    }

    /**
     * The configured length of one reservation block, in minutes.
     */
    public static function blockMinutes(): int
    {
        return (int) self::get('reservation.block_minutes', 15);
    }

    /**
     * Months of inactivity after which a logged-in client must reactivate before
     * booking. Inactivity is measured from the client's latest non-cancelled
     * reservation date.
     */
    public static function reactivationMonths(): int
    {
        return (int) self::get('reservation.reactivation_months', 12);
    }

    /**
     * Fallback recency window (in months) for a `clients`-visibility service that
     * does not set its own `existing_client_months`.
     */
    public static function existingClientMonths(): int
    {
        return (int) self::get('reservation.default_existing_client_months', 6);
    }

    /**
     * How many days into the future the public booking wizard offers slots.
     */
    public static function bookingWindowDays(): int
    {
        return (int) self::get('reservation.booking_window_days', 60);
    }

    /**
     * Minimum lead time (in hours) before a slot can be booked online.
     */
    public static function leadTimeHours(): int
    {
        return (int) self::get('reservation.lead_time_hours', 0);
    }

    /**
     * How many hours before the visit the customer confirmation-request e-mail goes out.
     */
    public static function confirmationHours(): int
    {
        return (int) self::get('reservation.confirmation_hours', 48);
    }

    /**
     * How many hours before the visit a confirmed reservation gets its reminder e-mail.
     */
    public static function reminderHours(): int
    {
        return (int) self::get('reservation.reminder_hours', 24);
    }

    /**
     * How many hours before the visit an unconfirmed reservation is automatically cancelled.
     */
    public static function autoCancelHours(): int
    {
        return (int) self::get('reservation.auto_cancel_hours', 24);
    }

    /**
     * Clinic-wide free-cancellation cutoff (hours before the visit) for customer
     * self-cancellation, used when a service has no CancellationRule of its own.
     */
    public static function cancelBeforeHours(): int
    {
        return (int) self::get('reservation.cancel_before_hours', 24);
    }

    /**
     * Storno fee as a percentage of the service price, charged when a customer
     * cancels inside the storno window and chooses to pay rather than provide a
     * doctor's note.
     */
    public static function stornoFeePercent(): int
    {
        return (int) self::get('reservation.storno_fee_percent', 50);
    }

    /**
     * IBAN the clinic collects QR-Platba payments to (e.g. „CZ…"). Empty until configured.
     */
    public static function iban(): string
    {
        return (string) (self::get('payments.iban') ?? '');
    }

    /**
     * Human-readable payee name shown alongside payment instructions.
     */
    public static function paymentRecipient(): string
    {
        return (string) (self::get('payments.recipient_name') ?? '');
    }

    /**
     * The QR/payment note (message for the recipient) template. May contain
     * {{ tokens }} resolved from the payable + payment (see App\Support\Payments\PaymentNote).
     */
    public static function qrMessage(): string
    {
        return (string) (self::get('payments.qr_message') ?? '');
    }

    /**
     * Default due window (in days) applied to requested payments (storno fees,
     * unpaid-visit requests) when they are created.
     */
    public static function paymentDueDays(): int
    {
        return (int) self::get('payments.due_days', 7);
    }

    /**
     * Fee percentage charged when a client no-shows a confirmed visit.
     */
    public static function noShowFeePercent(): int
    {
        return (int) self::get('payments.no_show_fee_percent', 100);
    }

    /**
     * Supplier identity printed on invoices/receipts (frozen into snapshots at issue time).
     */
    public static function supplierName(): string
    {
        return (string) (self::get('invoices.supplier_name') ?? '');
    }

    public static function supplierAddress(): string
    {
        return (string) (self::get('invoices.supplier_address') ?? '');
    }

    public static function supplierDic(): string
    {
        return (string) (self::get('invoices.supplier_dic') ?? '');
    }

    public static function supplierRegistration(): string
    {
        return (string) (self::get('invoices.supplier_registration') ?? '');
    }

    /**
     * Whether the practice invoices as a VAT payer. Off = single gross amounts
     * with the vat-note line; on = items carry rates and the PDF shows a recap.
     */
    public static function vatPayer(): bool
    {
        return (bool) self::get('invoices.vat_payer', false);
    }

    public static function defaultVatRate(): int
    {
        return (int) self::get('invoices.default_vat_rate', 21);
    }

    /**
     * The "Nejsme plátci DPH." line printed while the VAT-payer mode is off.
     */
    public static function vatNote(): string
    {
        return (string) (self::get('invoices.vat_note') ?? '');
    }

    /**
     * Czech-format bank account number shown next to the IBAN on documents.
     */
    public static function bankAccount(): string
    {
        return (string) (self::get('invoices.bank_account') ?? '');
    }

    public static function invoiceDueDays(): int
    {
        return (int) self::get('invoices.due_days', 14);
    }

    public static function invoiceTextBeforeItems(): string
    {
        return (string) (self::get('invoices.text_before_items') ?? '');
    }

    public static function invoiceTextAfterItems(): string
    {
        return (string) (self::get('invoices.text_after_items') ?? '');
    }

    public static function invoiceFooterThankYou(): string
    {
        return (string) (self::get('invoices.footer_thank_you') ?? '');
    }
}
