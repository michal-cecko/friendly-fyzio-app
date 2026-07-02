<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a reservation auto-creates a client account. It invites the client to
 * set a password (via the login page's "forgotten password" flow) so they can manage
 * their bookings, while making clear an account is not required to attend.
 */
class ClientAccountCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Váš účet ve Friendly Fyzio')
            ->greeting('Dobrý den,')
            ->line('na základě vaší rezervace jsme pro vás vytvořili účet, kde si můžete spravovat své rezervace.')
            ->line('Heslo si nastavíte přes odkaz „Zapomenuté heslo“ na přihlašovací stránce.')
            ->action('Přihlásit se', url('/prihlaseni'))
            ->line('Účet využívat nemusíte — na termín se můžete dostavit i bez přihlášení.');
    }
}
