<?php

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\Reservation;
use App\Support\EmailTemplateRenderer;
use App\Support\Reservations\ReservationEmailContext;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends a CMS-authored reservation e-mail (see App\Mason\Bricks\Email + EmailTemplate):
 * renders the template for the given trigger, substituting the reservation's data into
 * the {{ tokens }}, and mails the resulting HTML document. Generic across triggers so
 * confirmed/reminder/… can reuse it.
 */
class ReservationTemplateNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Reservation $reservation,
        public EmailTemplateKey $key,
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
            // The template row should always be seeded; fall back to a plain line rather than fail.
            return (new MailMessage)
                ->subject($this->key->defaultSubject())
                ->line($this->key->label());
        }

        $html = EmailTemplateRenderer::render($template, ReservationEmailContext::for($this->reservation));

        return (new MailMessage)
            ->subject($template->subject)
            ->view('emails.rendered', ['html' => $html]);
    }
}
