<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'number' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Give every payment a sequential numeric id and derive the variable symbol
        // from it (the VS is never hand-typed). A plain max()+1 keeps this cross-DB;
        // the unique index on `number` is the backstop against a concurrent collision.
        static::creating(function (self $payment): void {
            if ($payment->number === null) {
                $payment->number = (int) (static::max('number') ?? 0) + 1;
            }

            if (blank($payment->variable_symbol)) {
                $payment->variable_symbol = (string) $payment->number;
            }
        });
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
}
