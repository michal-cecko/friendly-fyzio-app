<?php

namespace App\Models;

use App\Contracts\Emailable;
use App\Contracts\Payable;
use App\Enums\BookingStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PayableType;
use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsPayable;
use App\Observers\LessonBookingObserver;
use App\Support\Emails\CopyRecipients;
use App\Support\Emails\EnrollmentEmailer;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Somebody bought a seat at a single {@see Lesson} — a standalone workshop or
 * jednorázová lekce, or one lesson of a course série sold as a drop-in. Course
 * participants are not booked this way: they enrol in the whole série and appear
 * on the lesson's roster as {@see LessonAttendance} rows instead.
 */
#[ObservedBy(LessonBookingObserver::class)]
class LessonBooking extends Model implements Emailable, Payable
{
    use Auditable, HasFactory, HasUuids, IsPayable;

    public function logTitle(): string
    {
        return trim(($this->client?->name ?? 'Přihláška').' · '.($this->lesson?->displayName() ?? ''), ' ·');
    }

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
        return $this->belongsTo(Lesson::class, 'lesson_id');
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

    public function sendTemplateEmail(EmailTemplateKey $key, ?CopyRecipients $copies = null): void
    {
        EnrollmentEmailer::send($this, $key, $copies);
    }

    public function payableType(): PayableType
    {
        return PayableType::LessonBooking;
    }

    public function paymentAmountDue(): int
    {
        return (int) ($this->lesson?->price ?? 0);
    }

    public function payableTitleContext(): array
    {
        $lesson = $this->lesson;

        return [
            'nazev' => (string) ($lesson?->invoice_title ?? $lesson?->displayName() ?? ''),
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
