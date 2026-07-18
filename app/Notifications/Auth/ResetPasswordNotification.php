<?php

namespace App\Notifications\Auth;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\CmsMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Renders the dashboard-editable "password_reset" template around Laravel's
 * native reset URL (route password.reset). Dispatched by the password broker
 * via {@see User::sendPasswordResetNotification()} — both from the public
 * "zapomenuté heslo" page and the admin reset action.
 */
class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $template = EmailTemplate::forKey(EmailTemplateKey::PasswordReset);

        if ($template === null) {
            return parent::toMail($notifiable);
        }

        $name = (string) ($notifiable->name ?? '');

        return CmsMail::render($template, [
            'jmeno' => Str::of($name)->before(' ')->toString() ?: $name,
            'odkaz' => $this->resetUrl($notifiable),
        ]);
    }
}
