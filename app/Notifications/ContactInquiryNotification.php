<?php

namespace App\Notifications;

use App\Models\ContactInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent on-demand to the clinic inbox (web.contact_email) when a visitor submits
 * the public Kontakt form. Reply-to is set to the visitor so the clinic can
 * answer directly from their mail client.
 */
class ContactInquiryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ContactInquiry $inquiry,
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
        $inquiry = $this->inquiry;

        $message = (new MailMessage)
            ->subject('Nová zpráva z webu')
            ->replyTo($inquiry->email, $inquiry->name)
            ->greeting('Nová zpráva z kontaktního formuláře')
            ->line("Jméno: {$inquiry->name}")
            ->line("E-mail: {$inquiry->email}");

        if (filled($inquiry->phone)) {
            $message->line("Telefon: {$inquiry->phone}");
        }

        return $message
            ->line('Zpráva:')
            ->line($inquiry->message);
    }
}
