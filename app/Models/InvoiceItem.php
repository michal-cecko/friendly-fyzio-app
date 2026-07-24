<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'invoice_id',
        'title',
        'description',
        'quantity',
        'unit_price',
        'total',
        'vat_rate',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'total' => 'integer',
            'vat_rate' => 'integer',
            'sort' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The stored line total and the invoice amount are always server-derived;
        // whatever the form submits is recomputed here (whole CZK, integer math).
        static::saving(function (self $item): void {
            $item->total = (int) $item->quantity * (int) $item->unit_price;
        });

        static::saved(fn (self $item) => $item->invoice?->recalculateAmount());
        static::deleted(fn (self $item) => $item->invoice?->recalculateAmount());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
