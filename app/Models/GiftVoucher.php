<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftVoucher extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'voucher_code',
        'value',
        'recipient_name',
        'recipient_email',
        'purchased_at',
        'expires_at',
        'redeemed_at',
        'credited_to_client_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    public function creditedToClient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credited_to_client_id');
    }
}
