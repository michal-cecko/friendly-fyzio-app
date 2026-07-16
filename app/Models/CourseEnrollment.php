<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\PayableType;
use App\Enums\PaymentStatus;
use App\Models\Concerns\IsPayable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseEnrollment extends Model implements Payable
{
    use HasFactory, HasUuids, IsPayable;

    protected $fillable = [
        'client_id',
        'series_id',
        'status',
        'payment_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseEnrollmentStatus::class,
            'payment_status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(CourseSeries::class, 'series_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class, 'enrollment_id');
    }

    public function payableType(): PayableType
    {
        return PayableType::CourseEnrollment;
    }

    public function paymentAmountDue(): int
    {
        return (int) ($this->series?->price ?? 0);
    }

    public function payableTitleContext(): array
    {
        $series = $this->series;

        return [
            'kurz' => (string) ($series?->invoice_title ?? $series?->course?->name ?? $series?->name ?? ''),
            'beh' => (string) ($series?->name ?? ''),
            'obdobi' => implode(' – ', array_filter([
                $series?->start_date?->format('d. m. Y'),
                $series?->end_date?->format('d. m. Y'),
            ])),
            'klient' => (string) ($this->client?->name ?? ''),
        ];
    }
}
