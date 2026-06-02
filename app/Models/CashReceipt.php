<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashReceipt extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'receipt_number',
        'invoice_id',
        'client_id',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
