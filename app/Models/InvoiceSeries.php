<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceSeries extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'prefix',
        'current_number',
        'reset_yearly',
        'last_reset_year',
    ];

    protected function casts(): array
    {
        return [
            'reset_yearly' => 'boolean',
            'current_number' => 'integer',
            'last_reset_year' => 'integer',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'series_id');
    }
}
