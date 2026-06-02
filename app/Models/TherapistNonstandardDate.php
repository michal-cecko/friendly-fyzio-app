<?php

namespace App\Models;

use Database\Factories\TherapistNonstandardDateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapistNonstandardDate extends Model
{
    /** @use HasFactory<TherapistNonstandardDateFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'therapist_id',
        'work_date',
        'start_time',
        'end_time',
        'room_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class, 'therapist_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
