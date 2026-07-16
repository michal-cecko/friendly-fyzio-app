<?php

namespace App\Notifications\Auth;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\CmsMail;
use Filament\Auth\Notifications\VerifyEmailChange;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Renders the dashboard-editable "email_change_verification" template instead of
 * Filament's default. This notification is delivered to an anonymous notifiable routed at
 * the new address, so the greeting name and new e-mail are recovered from the signed
 * verification URL ($this->url): its `id` param is the user key and its `email` param is
 * the encrypted new address (see Panel::getVerifyEmailChangeUrl()).
 */
class VerifyEmailChangeNotification extends VerifyEmailChange
{
    public function toMail($notifiable): MailMessage
    {
        $template = EmailTemplate::forKey(EmailTemplateKey::EmailChangeVerification);

        if ($template === null) {
            return parent::toMail($notifiable);
        }

        return CmsMail::render($template, [
            'jmeno' => $this->recipientFirstName(),
            'email' => $this->newEmailFromUrl(),
            'odkaz' => $this->url,
        ]);
    }

    private function recipientFirstName(): string
    {
        $id = $this->urlQueryParam('id');

        if ($id === null) {
            return '';
        }

        $name = (string) (User::query()->whereKey($id)->value('name') ?? '');

        return Str::of($name)->before(' ')->toString() ?: $name;
    }

    private function newEmailFromUrl(): string
    {
        $encrypted = $this->urlQueryParam('email');

        if ($encrypted === null) {
            return '';
        }

        try {
            return (string) decrypt($encrypted);
        } catch (\Throwable) {
            return '';
        }
    }

    private function urlQueryParam(string $key): ?string
    {
        $query = parse_url($this->url, PHP_URL_QUERY);

        if (! is_string($query)) {
            return null;
        }

        parse_str($query, $params);

        $value = $params[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
