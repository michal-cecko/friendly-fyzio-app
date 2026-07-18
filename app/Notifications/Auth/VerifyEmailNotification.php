<?php

namespace App\Notifications\Auth;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\CmsMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Renders the dashboard-editable "email_verification" template around
 * Laravel's native signed verification URL (route verification.verify).
 * Dispatched by {@see User::sendEmailVerificationNotification()}.
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
            'odkaz' => $this->verificationUrl($notifiable),
        ]);
    }
}
