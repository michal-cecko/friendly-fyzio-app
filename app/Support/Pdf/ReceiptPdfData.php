<?php

namespace App\Support\Pdf;

use App\Models\CashReceipt;
use App\Support\Invoices\ClientSnapshot;
use App\Support\Invoices\CzechAmountInWords;
use App\Support\Invoices\SupplierSnapshot;

/**
 * View model for the příjmový pokladní doklad Blade (Pencil node o8gRs3).
 */
final readonly class ReceiptPdfData
{
    /**
     * @param  array<string, mixed>  $supplier
     */
    private function __construct(
        public string $number,
        public string $issuedAt,
        public array $supplier,
        public string $clientName,
        public ?string $clientAddress,
        public string $amountFormatted,
        public string $amountInWords,
        public string $purpose,
        public string $methodLabel,
        public string $receivedAt,
        public ?string $receivedBy,
        public ?string $invoiceNumber,
        public string $footerInfo,
    ) {}

    public static function fromReceipt(CashReceipt $receipt): self
    {
        $receipt->loadMissing('invoice', 'payment', 'client');

        $supplier = $receipt->invoice?->supplier_snapshot ?? SupplierSnapshot::current();

        $clientAddress = $receipt->invoice?->client_snapshot['address']
            ?? ($receipt->client !== null ? ClientSnapshot::for($receipt->client)['address'] : null);

        return new self(
            number: (string) $receipt->receipt_number,
            issuedAt: $receipt->received_at->format('d. m. Y'),
            supplier: $supplier,
            clientName: (string) ($receipt->client_name ?? $receipt->client?->name ?? ''),
            clientAddress: $clientAddress,
            amountFormatted: InvoicePdfData::money((int) $receipt->amount),
            amountInWords: CzechAmountInWords::for((int) $receipt->amount),
            purpose: (string) ($receipt->purpose ?? ''),
            methodLabel: $receipt->payment?->method?->getLabel() ?? 'Hotově',
            receivedAt: $receipt->received_at->format('d. m. Y'),
            receivedBy: $receipt->received_by,
            invoiceNumber: $receipt->invoice?->invoice_number,
            footerInfo: InvoicePdfData::footerInfo($supplier),
        );
    }

    public static function footerInfoFor(CashReceipt $receipt): string
    {
        return InvoicePdfData::footerInfo($receipt->invoice?->supplier_snapshot ?? SupplierSnapshot::current());
    }
}
