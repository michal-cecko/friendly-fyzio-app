<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
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
        'amount',
        'status',
        'payment_method',
        'issued_at',
        'due_at',
        'paid_at',
        'invoiceable_type',
        'invoiceable_id',
    ];

    protected function casts(): array
    {
        return [
            'client_snapshot' => 'array',
            'amount' => 'integer',
            'status' => InvoiceStatus::class,
            'payment_method' => PaymentMethod::class,
            'issued_at' => 'date',
            'due_at' => 'date',
            'paid_at' => 'datetime',
        ];
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

    public function cashReceipt(): HasOne
    {
        return $this->hasOne(CashReceipt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
