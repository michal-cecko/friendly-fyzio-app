<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceSeries extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'prefix',
        'current_number',
        'reset_yearly',
        'last_reset_year',
        'document_type',
        'padding',
        'format',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'reset_yearly' => 'boolean',
            'current_number' => 'integer',
            'last_reset_year' => 'integer',
            'document_type' => DocumentType::class,
            'padding' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Only one default series per document type; saving a new default demotes
        // the previous one (simpler cross-DB than a partial unique index).
        static::saved(function (self $series): void {
            if ($series->is_default) {
                static::query()
                    ->whereKeyNot($series->getKey())
                    ->where('document_type', $series->document_type)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'series_id');
    }

    public function cashReceipts(): HasMany
    {
        return $this->hasMany(CashReceipt::class, 'series_id');
    }
}
