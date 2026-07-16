<?php

namespace App\Support\Invoices;

use App\Contracts\Payable;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\InvoiceSeries;
use App\Models\Payment;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Creates invoices. `fromPayable()` is the primary on-demand path: a PAID payable
 * gets an invoice for its full amount due with all of its payments linked (the
 * invoice documents the settled supply). `create()` powers the standalone admin
 * path, whose payment thread is a backing Unpaid payment. The variable symbol is
 * never derived from the invoice number — it always comes from the payment thread.
 */
final class InvoiceGenerator
{
    public function __construct(
        private readonly DocumentNumberAllocator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  overrides of the settings-driven defaults
     * @param  list<array{title: string, description?: ?string, quantity?: int, unit_price: int, vat_rate?: ?int, sort?: int}>  $items
     */
    public function create(InvoiceSeries $series, User $client, array $attributes = [], array $items = []): Invoice
    {
        return DB::transaction(function () use ($series, $client, $attributes, $items): Invoice {
            $issuedAt = isset($attributes['issued_at'])
                ? Carbon::parse($attributes['issued_at'])
                : today();

            $number = $this->numbers->allocate($series, $issuedAt);

            $invoice = Invoice::query()->create([
                'series_id' => $series->getKey(),
                'invoice_number' => $number,
                'client_id' => $client->getKey(),
                'client_snapshot' => $attributes['client_snapshot'] ?? ClientSnapshot::for($client),
                'supplier_snapshot' => SupplierSnapshot::current(),
                'amount' => 0,
                'status' => $attributes['status'] ?? InvoiceStatus::New,
                'payment_method' => $attributes['payment_method'] ?? PaymentMethod::Qr,
                'issued_at' => $issuedAt,
                'due_at' => $attributes['due_at'] ?? $issuedAt->copy()->addDays(Settings::invoiceDueDays()),
                'paid_at' => $attributes['paid_at'] ?? null,
                'invoiceable_type' => $attributes['invoiceable_type'] ?? null,
                'invoiceable_id' => $attributes['invoiceable_id'] ?? null,
                'text_before_items' => $attributes['text_before_items'] ?? Settings::invoiceTextBeforeItems(),
                'text_after_items' => $attributes['text_after_items'] ?? Settings::invoiceTextAfterItems(),
                'footer_note' => $attributes['footer_note'] ?? Settings::invoiceFooterThankYou(),
                'vat_note' => $attributes['vat_note'] ?? (Settings::vatPayer() ? null : Settings::vatNote()),
                'variable_symbol' => $attributes['variable_symbol'] ?? null,
            ]);

            foreach (array_values($items) as $sort => $item) {
                $invoice->items()->create([
                    'title' => $item['title'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => (int) $item['unit_price'],
                    'total' => 0,
                    'vat_rate' => $item['vat_rate']
                        ?? (Settings::vatPayer() ? Settings::defaultVatRate() : null),
                    'sort' => $item['sort'] ?? $sort,
                ]);
            }

            return $invoice->refresh();
        });
    }

    /**
     * The paid-only payable path: the invoice documents the settled record for
     * its full amount due, links every payment of the debt and inherits the
     * debt's shared variable symbol.
     */
    public function fromPayable(
        Payable&Model $payable,
        ?InvoiceSeries $series = null,
        ?CarbonInterface $issuedAt = null,
        ?CarbonInterface $dueAt = null,
    ): Invoice {
        if ($payable->invoice()->exists()) {
            throw new InvalidArgumentException('Záznam už má vystavenou fakturu.');
        }

        if (! $payable->hasPaidStatus()) {
            throw new InvalidArgumentException('Fakturu lze vystavit až k zaplacenému záznamu.');
        }

        $due = $payable->paymentAmountDue();

        if ($due <= 0) {
            throw new InvalidArgumentException('Záznam nemá žádnou částku k fakturaci.');
        }

        $series = $this->resolveSeries($series);
        $client = $payable->client()->first();

        if ($client === null) {
            throw new RuntimeException('Záznam nemá přiřazeného klienta.');
        }

        $line = PayableTitle::render($payable);

        return DB::transaction(function () use ($payable, $series, $issuedAt, $dueAt, $client, $line, $due): Invoice {
            $payments = $payable->payments()->orderBy('number')->get();
            $received = $payments->where('status', PaymentStatus::Paid);

            $paidAt = $received->max('paid_at')
                ?? $payable->getAttribute('paid_at')
                ?? now();

            $attributes = [
                'status' => InvoiceStatus::Paid,
                'paid_at' => $paidAt,
                'payment_method' => $received->sortByDesc('paid_at')->first()?->method ?? PaymentMethod::Qr,
                'variable_symbol' => $payments->first()?->variable_symbol,
                'invoiceable_type' => $payable->getMorphClass(),
                'invoiceable_id' => $payable->getKey(),
            ];

            if ($issuedAt !== null) {
                $attributes['issued_at'] = $issuedAt;
            }

            if ($dueAt !== null) {
                $attributes['due_at'] = $dueAt;
            }

            $invoice = $this->create($series, $client, $attributes, [[
                'title' => $line['title'],
                'description' => $line['description'],
                'quantity' => 1,
                'unit_price' => $due,
            ]]);

            if ($payments->isEmpty()) {
                // Hand-marked-paid payable without transfer rows: fabricate the
                // thread so the debt still has a VS and a matching payment record.
                // Method Qr on purpose — no auto-PPD may fire on fabricated data.
                $backing = $invoice->payments()->create([
                    'client_id' => $client->getKey(),
                    'amount' => $due,
                    'method' => PaymentMethod::Qr,
                    'status' => PaymentStatus::Paid,
                    'paid_at' => $paidAt,
                    'payable_type' => $payable->getMorphClass(),
                    'payable_id' => $payable->getKey(),
                ]);

                $invoice->forceFill(['variable_symbol' => $backing->variable_symbol])->saveQuietly();
            } else {
                foreach ($payments as $payment) {
                    $payment->forceFill(['invoice_id' => $invoice->getKey()])->saveQuietly();

                    // Cash provenance: a received cash payment carries a PPD — link it.
                    if ($payment->method === PaymentMethod::Cash && $payment->status === PaymentStatus::Paid) {
                        $payment->cashReceipt()->first()?->update(['invoice_id' => $invoice->getKey()]);
                    }
                }
            }

            return $invoice->refresh();
        });
    }

    /**
     * Convenience entry from a payment row: routes to the payable's invoice when
     * one is attached; unlinked payments (their payable was force-deleted) get a
     * single-line invoice documenting just that received transfer.
     */
    public function fromPayment(
        Payment $payment,
        ?InvoiceSeries $series = null,
        ?CarbonInterface $issuedAt = null,
        ?CarbonInterface $dueAt = null,
    ): Invoice {
        if ($payment->invoice_id !== null) {
            throw new InvalidArgumentException('Platba už má vystavenou fakturu.');
        }

        $payable = $payment->payable;

        if ($payable instanceof Payable) {
            return $this->fromPayable($payable, $series, $issuedAt, $dueAt);
        }

        if ($payment->status !== PaymentStatus::Paid) {
            throw new InvalidArgumentException('Fakturu lze vystavit až k přijaté platbě.');
        }

        $series = $this->resolveSeries($series);
        $client = $payment->client;

        if ($client === null) {
            throw new RuntimeException('Platba nemá přiřazeného klienta.');
        }

        return DB::transaction(function () use ($payment, $series, $issuedAt, $dueAt, $client): Invoice {
            $attributes = [
                'status' => InvoiceStatus::Paid,
                'paid_at' => $payment->paid_at,
                'payment_method' => $payment->method,
                'variable_symbol' => (string) $payment->variable_symbol,
            ];

            if ($issuedAt !== null) {
                $attributes['issued_at'] = $issuedAt;
            }

            if ($dueAt !== null) {
                $attributes['due_at'] = $dueAt;
            }

            $invoice = $this->create($series, $client, $attributes, [[
                'title' => $payment->payable_label ?? 'Platba č. '.$payment->number,
                'description' => null,
                'quantity' => 1,
                'unit_price' => (int) $payment->amount,
            ]]);

            $payment->update(['invoice_id' => $invoice->getKey()]);

            if ($payment->method === PaymentMethod::Cash) {
                $payment->cashReceipt()->first()?->update(['invoice_id' => $invoice->getKey()]);
            }

            return $invoice;
        });
    }

    /**
     * Guarantee the invoice a payment thread: standalone invoices get a backing
     * Unpaid payment carrying the debt's variable symbol (and receiving the QR /
     * bank-matching role). Idempotent — an existing linked payment is returned.
     */
    public function ensureBackingPayment(Invoice $invoice): Payment
    {
        $existing = $invoice->payments()->orderBy('number')->first();

        if ($existing !== null) {
            return $existing;
        }

        $payment = $invoice->payments()->create([
            'client_id' => $invoice->client_id,
            'amount' => (int) $invoice->amount,
            'method' => $invoice->payment_method ?? PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
            'due_at' => $invoice->due_at,
        ]);

        if (blank($invoice->variable_symbol)) {
            $invoice->forceFill(['variable_symbol' => $payment->variable_symbol])->saveQuietly();
        }

        return $payment;
    }

    private function resolveSeries(?InvoiceSeries $series): InvoiceSeries
    {
        $series ??= $this->numbers->defaultSeries(DocumentType::Invoice);

        if ($series === null) {
            throw new RuntimeException('Neexistuje žádná číselná řada pro faktury.');
        }

        return $series;
    }
}
