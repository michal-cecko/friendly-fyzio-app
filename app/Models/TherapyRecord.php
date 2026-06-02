<?php

namespace App\Models;

use Database\Factories\TherapyRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapyRecord extends Model
{
    /** @use HasFactory<TherapyRecordFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'reservation_id',
        'client_id',
        'therapist_id',
        'content',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class, 'therapist_id');
    }
}
