<?php

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Notifications\Concerns\HasCopyRecipients;
use App\Support\CmsMail;
use App\Support\Emails\CopyRecipients;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Sends a CMS-authored course/lesson/workshop e-mail: renders the template for
 * the given trigger with a pre-built token context. Token building lives in
 * EnrollmentEmailContext (+ PaymentEmailTokens for the payment box), so this
 * stays generic across the enrollment triggers and works for account holders
 * and on-demand (guest e-mail) notifiables alike.
 */
class EnrollmentTemplateNotification extends Notification
{
    use HasCopyRecipients, Queueable;

    /**
     * @param  array<string, string|HtmlString>  $tokens
     */
    public function __construct(
        public EmailTemplateKey $key,
        public array $tokens = [],
        public ?CopyRecipients $copies = null,
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
            return $this->applyCopies(
                (new MailMessage)
                    ->subject($this->key->defaultSubject())
                    ->line($this->key->label())
            );
        }

        return $this->applyCopies(CmsMail::render($template, $this->tokens));
    }
}
