<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Enums\BookingStatus;
use App\Enums\PayableType;
use App\Enums\PaymentStatus;
use App\Models\Concerns\IsPayable;
use App\Observers\OneTimeLessonBookingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[ObservedBy(OneTimeLessonBookingObserver::class)]
class OneTimeLessonBooking extends Model implements Payable
{
    use HasFactory, HasUuids, IsPayable;

    protected $fillable = [
        'client_id',
        'lesson_id',
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

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(OneTimeLesson::class, 'lesson_id');
    }

    public function payableType(): PayableType
    {
        return PayableType::OneTimeLessonBooking;
    }

    public function paymentAmountDue(): int
    {
        return (int) ($this->lesson?->price ?? 0);
    }

    public function payableTitleContext(): array
    {
        $lesson = $this->lesson;

        return [
            'lekce' => (string) ($lesson?->invoice_title ?? $lesson?->course?->name ?? ''),
            'datum' => $lesson?->lesson_date?->format('d. m. Y') ?? '',
            'cas' => $lesson?->start_time ? Carbon::parse((string) $lesson->start_time)->format('H:i') : '',
            'klient' => (string) ($this->client?->name ?? ''),
        ];
    }

    public function payableTherapist(): ?User
    {
        return $this->lesson?->instructor;
    }
}
