<?php

namespace App\Notifications\Auth;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Support\CmsMail;
use Filament\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Renders the dashboard-editable "email_verification" template instead of Filament's
 * default. Bound in place of {@see VerifyEmail} in the container, so it keeps Filament's
 * signed verification URL ($this->url) while letting admins edit the copy.
 */
class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $template = EmailTemplate::forKey(EmailTemplateKey::EmailVerification);

        if ($template === null) {
            return parent::toMail($notifiable);
        }

        $name = (string) ($notifiable->name ?? '');

        return CmsMail::render($template, [
            'jmeno' => Str::of($name)->before(' ')->toString() ?: $name,
            'odkaz' => $this->url,
        ]);
    }
}
