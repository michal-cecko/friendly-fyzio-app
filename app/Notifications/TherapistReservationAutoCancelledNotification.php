<?php

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\Reservation;
use App\Support\CmsMail;
use App\Support\Reservations\TherapistReservationEmailContext;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Therapist notice that a reservation was auto-cancelled (unconfirmed by the
 * cutoff), rendered from the seeded CMS "therapist_reservation_auto_cancelled"
 * template. The greeting addresses the therapist; the detail box describes the
 * client + appointment.
 */
class TherapistReservationAutoCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(public Reservation $reservation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = EmailTemplateKey::TherapistReservationAutoCancelled;
        $template = EmailTemplate::forKey($key);

        if ($template === null) {
            return (new MailMessage)
                ->subject($key->defaultSubject())
                ->line($key->label());
        }

        return CmsMail::render($template, TherapistReservationEmailContext::for($this->reservation, [
            'auto_zruseni_hodin' => (string) Settings::autoCancelHours(),
            'duvod' => (string) ($this->reservation->cancellation_reason ?? ''),
        ]));
    }
}
