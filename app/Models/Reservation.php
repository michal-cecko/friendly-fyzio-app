<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'client_id',
        'service_id',
        'therapist_id',
        'room_id',
        'reservation_date',
        'start_time',
        'end_time',
        'status',
        'payment_status',
        'is_control_therapy',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'status' => ReservationStatus::class,
            'payment_status' => PaymentStatus::class,
            'is_control_therapy' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class, 'therapist_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function therapyRecord(): HasOne
    {
        return $this->hasOne(TherapyRecord::class);
    }

    public function payments(): HasMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function invoice(): HasOne
    {
        return $this->morphOne(Invoice::class, 'invoiceable');
    }
}
