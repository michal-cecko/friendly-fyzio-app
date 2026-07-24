<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationRule extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'service_id',
        'cancel_before_hours',
    ];

    protected function casts(): array
    {
        return [
            'cancel_before_hours' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
