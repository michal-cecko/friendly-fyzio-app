<?php

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\Reservation;
use App\Support\CmsMail;
use App\Support\Reservations\TherapistReservationEmailContext;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends a therapist-facing CMS reservation e-mail (the therapist_reservation_* templates):
 * renders the template for the given trigger, substituting the reservation's data into the
 * {{ tokens }}. Generic across triggers so created/confirmed/cancelled/changed reuse it —
 * the therapist counterpart of {@see ReservationTemplateNotification}.
 */
class TherapistReservationTemplateNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, string>  $extraTokens  Trigger-specific tokens merged over the
     *                                              therapist base context (e.g. storno_reseni).
     */
    public function __construct(
        public Reservation $reservation,
        public EmailTemplateKey $key,
        public array $extraTokens = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = EmailTemplate::forKey($this->key);

        if ($template === null) {
            return (new MailMessage)
                ->subject($this->key->defaultSubject())
                ->line($this->key->label());
        }

        return CmsMail::render($template, TherapistReservationEmailContext::for($this->reservation, $this->extraTokens));
    }
}
