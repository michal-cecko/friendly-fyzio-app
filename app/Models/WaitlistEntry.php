<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WaitlistEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'client_id',
        'waitlistable_type',
        'waitlistable_id',
        'notified_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function waitlistable(): MorphTo
    {
        return $this->morphTo();
    }
}
