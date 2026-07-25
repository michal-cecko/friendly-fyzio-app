<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBlocking extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'room_id',
        'reason',
        'is_recurring',
        'day_of_week',
        'week_type',
        'start_time',
        'end_time',
        'start_at',
        'end_at',
    ];

    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'day_of_week' => DayOfWeek::class,
            'week_type' => WeekType::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function logTitle(): string
    {
        return $this->reason ?: 'Blokace místnosti';
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
