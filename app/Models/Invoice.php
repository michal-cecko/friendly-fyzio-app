<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Invoice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'series_id',
        'invoice_number',
        'client_id',
        'client_snapshot',
        'supplier_snapshot',
        'amount',
        'status',
        'payment_method',
        'issued_at',
        'due_at',
        'paid_at',
        'invoiceable_type',
        'invoiceable_id',
        'text_before_items',
        'text_after_items',
        'footer_note',
        'vat_note',
        'variable_symbol',
    ];

    protected function casts(): array
    {
        return [
            'client_snapshot' => 'array',
            'supplier_snapshot' => 'array',
            'amount' => 'integer',
            'status' => InvoiceStatus::class,
            'payment_method' => PaymentMethod::class,
            'issued_at' => 'date',
            'due_at' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Re-derive the invoice amount from its line items. Quiet save — the amount
     * is a denormalised sum, not an edit worth broadcasting through model events.
     */
    public function recalculateAmount(): void
    {
        $this->forceFill(['amount' => (int) $this->items()->sum('total')])->saveQuietly();

        $this->syncBackingPayment();
    }

    /**
     * Keep a lone unpaid (backing) payment mirroring the invoice, so the QR code
     * and payment instructions stay correct after the items are edited. Threads
     * with several payments or already-received money are real transfers and are
     * never touched.
     */
    private function syncBackingPayment(): void
    {
        $payments = $this->payments()->get();

        if ($payments->count() !== 1) {
            return;
        }

        $backing = $payments->sole();

        if ($backing->status !== PaymentStatus::Unpaid) {
            return;
        }

        $backing->forceFill([
            'amount' => (int) $this->amount,
            'due_at' => $this->due_at,
        ])->saveQuietly();
    }

    /**
     * Forward-only paid derivation: once the linked received payments cover the
     * amount, the invoice flips to Zaplacená. Unmarking or deleting a payment
     * never reverts an issued document — that stays a manual edit.
     */
    public function refreshPaidStatus(): void
    {
        if ($this->status === InvoiceStatus::Paid || (int) $this->amount <= 0) {
            return;
        }

        $received = $this->payments()->where('status', PaymentStatus::Paid->value);

        if ((int) $received->sum('amount') < (int) $this->amount) {
            return;
        }

        $this->forceFill([
            'status' => InvoiceStatus::Paid,
            'paid_at' => $received->max('paid_at') ?? now(),
        ])->saveQuietly();
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(InvoiceSeries::class, 'series_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function invoiceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort');
    }

    public function cashReceipt(): HasOne
    {
        return $this->hasOne(CashReceipt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
