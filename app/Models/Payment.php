<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Observers\PaymentObserver;
use App\Support\Invoices\PayableTitle;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[ObservedBy(PaymentObserver::class)]
class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'client_id',
        'amount',
        'method',
        'number',
        'variable_symbol',
        'status',
        'invoice_id',
        'payable_type',
        'payable_id',
        'payable_label',
        'paid_at',
        'due_at',
        'overdue_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'number' => 'integer',
            'paid_at' => 'datetime',
            'due_at' => 'date',
            'overdue_notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Give every payment a sequential numeric id. A plain max()+1 keeps this
        // cross-DB; the unique index on `number` is the backstop against a
        // concurrent collision.
        static::creating(function (self $payment): void {
            if ($payment->number === null) {
                $payment->number = (int) (static::max('number') ?? 0) + 1;
            }

            if (blank($payment->variable_symbol)) {
                $payment->variable_symbol = self::resolveVariableSymbol($payment);
            }

            // Snapshot what the payment is for, so the record still reads sensibly
            // once its payable is force-deleted and the link is dropped.
            if (blank($payment->payable_label) && $payment->payable instanceof Payable) {
                $payment->payable_label = PayableTitle::render($payment->payable)['title'];
            }
        });
    }

    /**
     * The variable symbol identifies the DEBT, not the transfer: a debt's first
     * payment mints it (from its own sequential number) and the invoice plus every
     * further payment toward the same debt inherit it — so an invoice settled by
     * several transfers still carries one symbol.
     */
    private static function resolveVariableSymbol(self $payment): string
    {
        if ($payment->invoice_id !== null) {
            $inherited = Invoice::query()->whereKey($payment->invoice_id)->value('variable_symbol');

            if (filled($inherited)) {
                return (string) $inherited;
            }
        }

        $payable = $payment->payable;

        if ($payable instanceof Payable) {
            $inherited = $payable->invoice()->value('variable_symbol');

            if (filled($inherited)) {
                return (string) $inherited;
            }

            $inherited = $payable->payments()->orderByDesc('number')->value('variable_symbol');

            if (filled($inherited)) {
                return (string) $inherited;
            }
        }

        return (string) $payment->number;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function cashReceipt(): HasOne
    {
        return $this->hasOne(CashReceipt::class);
    }
}
