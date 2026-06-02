<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'service_id',
        'cancel_before_hours',
        'auto_cancel_after_days',
    ];

    protected function casts(): array
    {
        return [
            'cancel_before_hours' => 'integer',
            'auto_cancel_after_days' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
