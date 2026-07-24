<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashReceipt extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'receipt_number',
        'invoice_id',
        'series_id',
        'payment_id',
        'client_id',
        'client_name',
        'purpose',
        'received_by',
        'amount',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'received_at' => 'date',
        ];
    }

    public function logTitle(): string
    {
        return $this->receipt_number ?: 'Pokladní doklad';
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(InvoiceSeries::class, 'series_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
