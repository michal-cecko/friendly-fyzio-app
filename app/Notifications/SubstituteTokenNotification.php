<?php

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Support\CmsMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends a CMS-authored substitute-entry e-mail (token generated / redeemed).
 */
class SubstituteTokenNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, string>  $tokens
     */
    public function __construct(
        public EmailTemplateKey $key,
        public array $tokens = [],
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

        return CmsMail::render($template, $this->tokens);
    }
}
