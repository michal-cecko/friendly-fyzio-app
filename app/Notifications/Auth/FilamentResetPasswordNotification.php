<?php

namespace App\Notifications\Auth;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\CmsMail;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Renders the dashboard-editable "password_reset" template around Filament's
 * reset URL (/admin/password-reset/reset). The admin panel's "forgot password"
 * page bypasses {@see User::sendPasswordResetNotification()} and
 * dispatches Filament's own notification directly, so this subclass is bound
 * over it in the container (see AppServiceProvider::register()).
 */
class FilamentResetPasswordNotification extends ResetPassword
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
