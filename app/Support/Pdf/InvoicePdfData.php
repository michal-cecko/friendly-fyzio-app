<?php

namespace App\Support\Pdf;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Support\Invoices\SupplierSnapshot;
use App\Support\Payments\QrPlatba;

/**
 * Everything the invoice Blade needs, preformatted (Czech money/dates) and frozen
 * from the invoice's stored snapshots — rendering never reads live settings for
 * historical data. The document is deliberately status-agnostic: payment details
 * always render and paid state never shows on the invoice itself.
 */
final readonly class InvoicePdfData
{
    /**
     * @param  array<string, mixed>  $supplier
     * @param  array<string, mixed>  $customer
     * @param  list<array{title: string, description: ?string, quantity: int, unitPrice: string, total: string, vatRate: ?int}>  $items
     * @param  list<array{rate: int, base: string, vat: string, total: string}>  $vatRows
     */
    private function __construct(
        public string $number,
        public string $issuedAt,
        public string $dueAt,
        public array $supplier,
        public array $customer,
        public array $items,
        public string $totalFormatted,
        public bool $showVat,
        public array $vatRows,
        public ?string $textBefore,
        public ?string $textAfter,
        public ?string $footerNote,
        public string $footerInfo,
        public ?string $bankAccount,
        public ?string $iban,
        public ?string $variableSymbol,
        public ?string $qrDataUri,
    ) {}

    public static function fromInvoice(Invoice $invoice): self
    {
        $invoice->loadMissing('items', 'client');

        $supplier = $invoice->supplier_snapshot ?? SupplierSnapshot::current();
        $customer = $invoice->client_snapshot ?? [];

        $items = $invoice->items->map(fn ($item): array => [
            'title' => (string) $item->title,
            'description' => $item->description,
            'quantity' => (int) $item->quantity,
            'unitPrice' => self::money((int) $item->unit_price),
            'total' => self::money((int) $item->total),
            'vatRate' => $item->vat_rate,
        ])->all();

        $showVat = $invoice->items->contains(fn ($item): bool => $item->vat_rate !== null);

        // Gross prices: back the base out of each rate group (§37 computed method).
        $vatRows = $invoice->items
            ->filter(fn ($item): bool => $item->vat_rate !== null)
            ->groupBy('vat_rate')
            ->map(function ($group, int $rate): array {
                $gross = (int) $group->sum('total');
                $base = (int) round($gross * 100 / (100 + $rate));

                return [
                    'rate' => $rate,
                    'base' => self::money($base),
                    'vat' => self::money($gross - $base),
                    'total' => self::money($gross),
                ];
            })
            ->values()
            ->all();

        $iban = QrPlatba::ibanForInvoice($invoice);

        return new self(
            number: (string) $invoice->invoice_number,
            issuedAt: $invoice->issued_at->format('d. m. Y'),
            dueAt: $invoice->due_at->format('d. m. Y'),
            supplier: $supplier,
            customer: $customer,
            items: $items,
            totalFormatted: self::money((int) $invoice->amount),
            showVat: $showVat,
            vatRows: $vatRows,
            textBefore: $invoice->text_before_items,
            textAfter: $invoice->text_after_items,
            footerNote: $invoice->footer_note,
            footerInfo: self::footerInfo($supplier, $invoice->vat_note),
            bankAccount: filled($supplier['bank_account'] ?? null) ? (string) $supplier['bank_account'] : null,
            iban: $iban !== '' ? $iban : null,
            variableSymbol: $invoice->variable_symbol,
            qrDataUri: $invoice->payment_method === PaymentMethod::Qr
                && $iban !== ''
                && (int) $invoice->amount > 0
                ? QrPlatba::dataUriForInvoice($invoice)
                : null,
        );
    }

    public static function money(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' Kč';
    }

    /**
     * The single info line for the running page footer: an optional lead note
     * (the VAT note on invoices) followed by the supplier identity.
     *
     * @param  array<string, mixed>  $supplier
     */
    public static function footerInfo(array $supplier, ?string $lead = null): string
    {
        return implode(' · ', array_filter([
            $lead,
            $supplier['name'] ?? null,
            filled($supplier['ico'] ?? null) ? 'IČO: '.$supplier['ico'] : null,
            filled($supplier['dic'] ?? null) ? 'DIČ: '.$supplier['dic'] : null,
            $supplier['registration'] ?? null,
        ], fn ($part): bool => filled($part)));
    }

    public static function footerInfoFor(Invoice $invoice): string
    {
        return self::footerInfo($invoice->supplier_snapshot ?? SupplierSnapshot::current(), $invoice->vat_note);
    }
}
