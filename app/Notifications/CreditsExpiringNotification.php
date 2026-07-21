<?php

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Models\CreditTransaction;
use App\Models\EmailTemplate;
use App\Support\CmsMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Reminds a client that their credit is about to expire — the CMS
 * "credits_expiring" template with the current balance and the soonest expiry
 * date. Sent N days ahead by the credits:notify-expiring command.
 */
class CreditsExpiringNotification extends Notification
{
    use Queueable;

    /**
     * @param  CreditTransaction  $creditTransaction  the soonest-expiring top-up (drives {{ platnost }} + activity-log association)
     * @param  int  $balance  the client's current credit balance about to be at risk
     */
    public function __construct(
        public CreditTransaction $creditTransaction,
        public int $balance,
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
        $key = EmailTemplateKey::CreditsExpiring;
        $template = EmailTemplate::forKey($key);

        if ($template === null) {
            return (new MailMessage)
                ->subject($key->defaultSubject())
                ->line($key->label());
        }

        $clientName = (string) ($this->creditTransaction->client?->name ?? '');

        $context = [
            'jmeno' => Str::of($clientName)->before(' ')->toString() ?: $clientName,
            'kredit' => number_format($this->balance, 0, ',', ' '),
            'platnost' => $this->creditTransaction->expires_at?->translatedFormat('j. F Y') ?? '',
            'odkaz' => route('zone.credits'),
        ];

        return CmsMail::render($template, $context);
    }
}
