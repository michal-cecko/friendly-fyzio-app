<?php

namespace App\Notifications\Auth;

use App\Enums\EmailTemplateKey;
use App\Livewire\Zone\Profile;
use App\Models\EmailTemplate;
use App\Support\CmsMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Public client-zone twin of {@see VerifyEmailNotification}: renders the
 * dashboard-editable "email_change_verification" template around Laravel's
 * native signed verification URL (route verification.verify), so a customer who
 * changes their address in /muj-ucet/profil gets e-mail-change wording instead
 * of the registration copy. Dispatched by {@see Profile}.
 *
 * The zone flow updates the address up front, so the new e-mail the template
 * greets is simply the notifiable's current one.
 */
class VerifyEmailChangeForClientNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $template = EmailTemplate::forKey(EmailTemplateKey::EmailChangeVerification);

        if ($template === null) {
            return parent::toMail($notifiable);
        }

        $name = (string) ($notifiable->name ?? '');

        return CmsMail::render($template, [
            'jmeno' => Str::of($name)->before(' ')->toString() ?: $name,
            'email' => (string) $notifiable->email,
            'odkaz' => $this->verificationUrl($notifiable),
        ]);
    }
}
