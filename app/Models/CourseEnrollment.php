<?php

namespace App\Models;

use App\Contracts\Emailable;
use App\Contracts\Payable;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PayableType;
use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsPayable;
use App\Observers\CourseEnrollmentObserver;
use App\Support\Emails\EnrollmentEmailer;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(CourseEnrollmentObserver::class)]
class CourseEnrollment extends Model implements Emailable, Payable
{
    use Auditable, HasFactory, HasUuids, IsPayable;

    public function logTitle(): string
    {
        return trim(($this->client?->name ?? 'Přihláška').' · '.($this->series?->name ?? ''), ' ·');
    }

    protected $fillable = [
        'client_id',
        'series_id',
        'status',
        'payment_status',
        'paid_at',
        'note',
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

    public function emailRecipientAddress(): ?string
    {
        return $this->client?->email;
    }

    public function emailRecipientName(): ?string
    {
        return $this->client?->name;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function emailTemplateGroups(): array
    {
        return EnrollmentEmailer::templateGroups($this);
    }

    public function sendTemplateEmail(EmailTemplateKey $key): void
    {
        EnrollmentEmailer::send($this, $key);
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

    public function payableTherapist(): ?User
    {
        return $this->series?->course?->instructor;
    }
}
