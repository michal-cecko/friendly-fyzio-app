<?php

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Notifications\Concerns\HasCopyRecipients;
use App\Support\CmsMail;
use App\Support\Emails\CopyRecipients;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Sent when a reservation auto-creates a client account. It invites the client to
 * set a password (via the login page's "forgotten password" flow) so they can manage
 * their bookings, while making clear an account is not required to attend. Rendered from
 * the dashboard-editable "account_created" CMS template.
 */
class ClientAccountCreatedNotification extends Notification implements ShouldQueue
{
    use HasCopyRecipients, Queueable;

    public function __construct(public ?CopyRecipients $copies = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = EmailTemplate::forKey(EmailTemplateKey::AccountCreated);

        if ($template === null) {
            return $this->applyCopies((new MailMessage)
                ->subject(EmailTemplateKey::AccountCreated->defaultSubject())
                ->greeting('Dobrý den,')
                ->line('na základě vaší rezervace jsme pro vás vytvořili účet, kde si můžete spravovat své rezervace.')
                ->line('Heslo si nastavíte přes odkaz „Zapomenuté heslo“ na přihlašovací stránce.')
                ->action('Přihlásit se', url('/prihlaseni'))
                ->line('Účet využívat nemusíte — na termín se můžete dostavit i bez přihlášení.'));
        }

        $name = (string) ($notifiable->name ?? '');

        return $this->applyCopies(CmsMail::render($template, [
            'jmeno' => Str::of($name)->before(' ')->toString() ?: $name,
            'odkaz' => url('/prihlaseni'),
        ]));
    }
}
