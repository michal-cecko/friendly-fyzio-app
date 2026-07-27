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
        'created_by',
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

    protected static function booted(): void
    {
        // A blocking belongs to nobody in particular, so the person who added it
        // is its owner: that is what lets a therapist manage their own blockings
        // without touching anyone else's. Imports and seeders run without a user
        // and leave the column empty — those are for admins only.
        static::creating(function (self $blocking): void {
            if ($blocking->created_by === null) {
                $blocking->created_by = auth()->id();
            }
        });
    }

    public function logTitle(): string
    {
        return $this->reason ?: 'Blokace místnosti';
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
