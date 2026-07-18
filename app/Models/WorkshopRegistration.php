<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Enums\BookingStatus;
use App\Enums\PayableType;
use App\Enums\PaymentStatus;
use App\Models\Concerns\IsPayable;
use App\Observers\WorkshopRegistrationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[ObservedBy(WorkshopRegistrationObserver::class)]
class WorkshopRegistration extends Model implements Payable
{
    use HasFactory, HasUuids, IsPayable;

    protected $fillable = [
        'client_id',
        'workshop_id',
        'status',
        'payment_status',
        'paid_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function payableType(): PayableType
    {
        return PayableType::WorkshopRegistration;
    }

    public function paymentAmountDue(): int
    {
        return (int) ($this->workshop?->price ?? 0);
    }

    public function payableTitleContext(): array
    {
        $workshop = $this->workshop;

        return [
            'workshop' => (string) ($workshop?->invoice_title ?? $workshop?->name ?? ''),
            'datum' => $workshop?->workshop_date?->format('d. m. Y') ?? '',
            'cas' => $workshop?->start_time ? Carbon::parse((string) $workshop->start_time)->format('H:i') : '',
            'klient' => (string) ($this->client?->name ?? ''),
        ];
    }

    public function payableTherapist(): ?User
    {
        return $this->workshop?->instructor;
    }
}
