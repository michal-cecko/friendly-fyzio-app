<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditTransaction extends Model
{
    use Auditable, HasFactory, HasUuids;

    public function logTitle(): string
    {
        return 'Kredit '.number_format((int) $this->amount, 0, ',', ' ').' Kč'.($this->client ? ' · '.$this->client->name : '');
    }

    protected $fillable = [
        'client_id',
        'amount',
        'type',
        'description',
        'expires_at',
        'related_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'type' => CreditTransactionType::class,
            'expires_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * For Expiration rows: the top-up this expiration consumed.
     */
    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_transaction_id');
    }

    /**
     * For TopUp rows: the expiration row(s) that consumed this top-up — the
     * existence marker the credits:expire command keys on.
     */
    public function expirations(): HasMany
    {
        return $this->hasMany(self::class, 'related_transaction_id');
    }
}
