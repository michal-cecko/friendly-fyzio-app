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
use App\Observers\OneOffEventBookingObserver;
use App\Support\Emails\CopyRecipients;
use App\Support\Emails\EnrollmentEmailer;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[ObservedBy(OneOffEventBookingObserver::class)]
class OneOffEventBooking extends Model implements Emailable, Payable
{
    use Auditable, HasFactory, HasUuids, IsPayable;

    public function logTitle(): string
    {
        return trim(($this->client?->name ?? 'Přihláška').' · '.($this->event?->name ?? ''), ' ·');
    }

    protected $fillable = [
        'client_id',
        'one_off_event_id',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(OneOffEvent::class, 'one_off_event_id');
    }

    public function payableType(): PayableType
    {
        return PayableType::OneOffEventBooking;
    }

    public function paymentAmountDue(): int
    {
        return (int) ($this->event?->price ?? 0);
    }

    public function payableTitleContext(): array
    {
        $event = $this->event;

        return [
            'nazev' => (string) ($event?->invoice_title ?? $event?->name ?? ''),
            'datum' => $event?->event_date?->format('d. m. Y') ?? '',
            'cas' => $event?->start_time ? Carbon::parse((string) $event->start_time)->format('H:i') : '',
            'klient' => (string) ($this->client?->name ?? ''),
        ];
    }

    public function payableTherapist(): ?User
    {
        return $this->event?->instructor;
    }
}
