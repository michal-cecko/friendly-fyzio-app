<?php

namespace App\Support\Invoices;

use App\Contracts\Payable;
use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CashReceipt;
use App\Models\Invoice;
use App\Models\InvoiceSeries;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Issues příjmové pokladní doklady (PPD). Idempotent per source: re-issuing for
 * a payment/invoice that already has one returns the existing receipt.
 */
final class CashReceiptGenerator
{
    public function __construct(private readonly DocumentNumberAllocator $numbers) {}

    public function fromPayment(Payment $payment, ?InvoiceSeries $series = null): CashReceipt
    {
        if ($payment->method !== PaymentMethod::Cash) {
            throw new InvalidArgumentException('Pokladní doklad lze vystavit jen k hotovostní platbě.');
        }

        if ($payment->status !== PaymentStatus::Paid) {
            throw new InvalidArgumentException('Pokladní doklad lze vystavit jen k přijaté platbě.');
        }

        $existing = $payment->cashReceipt()->first();

        if ($existing !== null) {
            return $existing;
        }

        $series = $this->resolveSeries($series);
        $payable = $payment->payable;

        $purpose = match (true) {
            $payment->invoice !== null => 'Úhrada faktury č. '.$payment->invoice->invoice_number,
            $payable instanceof Payable => PayableTitle::render($payable)['title'],
            default => 'Platba č. '.$payment->number,
        };

        return DB::transaction(fn (): CashReceipt => CashReceipt::query()->create([
            'receipt_number' => $this->numbers->allocate($series, $payment->paid_at),
            'invoice_id' => $payment->invoice_id,
            'series_id' => $series->getKey(),
            'payment_id' => $payment->getKey(),
            'client_id' => $payment->client_id,
            'client_name' => $payment->client !== null ? ClientSnapshot::for($payment->client)['name'] : null,
            'purpose' => $purpose,
            'received_by' => auth()->user()?->name,
            'amount' => (int) $payment->amount,
            'received_at' => $payment->paid_at?->toDateString() ?? today(),
        ]));
    }

    public function fromInvoice(Invoice $invoice, ?InvoiceSeries $series = null): CashReceipt
    {
        $existing = $invoice->cashReceipt()->first();

        if ($existing !== null) {
            return $existing;
        }

        $series = $this->resolveSeries($series);

        return DB::transaction(fn (): CashReceipt => CashReceipt::query()->create([
            'receipt_number' => $this->numbers->allocate($series),
            'invoice_id' => $invoice->getKey(),
            'series_id' => $series->getKey(),
            'payment_id' => null,
            'client_id' => $invoice->client_id,
            'client_name' => $invoice->client_snapshot['name'] ?? $invoice->client?->name,
            'purpose' => 'Úhrada faktury č. '.$invoice->invoice_number,
            'received_by' => auth()->user()?->name,
            'amount' => (int) $invoice->amount,
            'received_at' => today(),
        ]));
    }

    private function resolveSeries(?InvoiceSeries $series): InvoiceSeries
    {
        $series ??= $this->numbers->defaultSeries(DocumentType::Receipt);

        if ($series === null) {
            throw new RuntimeException('Neexistuje žádná číselná řada pro pokladní doklady.');
        }

        return $series;
    }
}
