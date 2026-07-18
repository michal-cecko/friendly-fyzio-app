<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditAccount extends Model
{
    use Auditable, HasFactory, HasUuids;

    public function logTitle(): string
    {
        return 'Kreditní účet'.($this->client ? ' · '.$this->client->name : '');
    }

    protected $fillable = ['client_id', 'balance'];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class, 'client_id', 'client_id');
    }
}
