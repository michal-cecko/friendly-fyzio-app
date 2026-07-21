<?php

namespace App\Notifications;

use App\Listeners\LogSentEmail;
use App\Support\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A free-form e-mail composed by an admin (subject + rich body + optional CC/BCC),
 * wrapped in the fixed FriendlyFyzio email layout. Generic across every emailable
 * record: the concerned record is held as a public property so {@see LogSentEmail}
 * attaches the `email_sent` activity to it, and delivery reuses the `emails.rendered`
 * view shape so the logged body preview renders unchanged.
 */
class CustomEmailNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     */
    public function __construct(
        public ?Model $record,
        public string $emailSubject,
        public string $bodyHtml,
        public array $cc = [],
        public array $bcc = [],
        public ?string $replyToAddress = null,
        public ?string $replyToName = null,
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
        $mail = (new MailMessage)
            ->subject($this->emailSubject)
            ->view('emails.rendered', ['html' => EmailTemplateRenderer::renderHtml($this->bodyHtml, $this->emailSubject)]);

        if ($this->cc !== []) {
            $mail->cc($this->cc);
        }

        if ($this->bcc !== []) {
            $mail->bcc($this->bcc);
        }

        // Route replies to the staff member who composed the e-mail; falls back to the
        // default From address when the sender has no e-mail.
        if ($this->replyToAddress !== null) {
            $mail->replyTo($this->replyToAddress, $this->replyToName);
        }

        return $mail;
    }
}
