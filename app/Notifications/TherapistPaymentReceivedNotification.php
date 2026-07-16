<?php

namespace App\Notifications;

use App\Contracts\Payable;
use App\Enums\EmailTemplateKey;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Models\EmailTemplate;
use App\Models\Payment;
use App\Support\EmailTemplateRenderer;
use App\Support\Invoices\PayableTitle;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Therapist-facing "client payment received" notice, rendered from the seeded
 * CMS "therapist_payment_received" template.
 */
class TherapistPaymentReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = EmailTemplateKey::TherapistPaymentReceived;
        $template = EmailTemplate::forKey($key);

        if ($template === null) {
            return (new MailMessage)
                ->subject($key->defaultSubject())
                ->line($key->label());
        }

        $payable = $this->payment->payable;
        $client = $this->payment->client;

        $context = [
            'klient' => (string) ($client?->name ?? ''),
            'za_co' => $payable instanceof Payable
                ? PayableTitle::render($payable)['title']
                : 'platbu č. '.$this->payment->number,
            'castka' => number_format((int) $this->payment->amount, 0, ',', ' ').' Kč',
            'datum_platby' => ($this->payment->paid_at ?? now())->format('d. m. Y'),
            'zpusob_platby' => $this->payment->method->getLabel(),
            'odkaz_klient' => $client !== null
                ? ClientResource::getUrl('view', ['record' => $client])
                : ClientResource::getUrl('index'),
        ];

        return (new MailMessage)
            ->subject($template->subject)
            ->view('emails.rendered', ['html' => EmailTemplateRenderer::render($template, $context)]);
    }
}
