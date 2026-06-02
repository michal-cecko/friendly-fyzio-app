<?php

namespace App\Models;

use Database\Factories\ClientProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProfile extends Model
{
    /** @use HasFactory<ClientProfileFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'date_of_birth',
        'address_city',
        'occupation',
        'weight',
        'height',
        'company_ico',
        'company_dic',
        'billing_address',
        'anamnesis',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'weight' => 'decimal:2',
            'height' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
