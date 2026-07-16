<?php

namespace App\Notifications;

use App\Contracts\Payable;
use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\EmailTemplateRenderer;
use App\Support\Invoices\PayableTitle;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Therapist-facing overdue notice, rendered from the seeded CMS
 * "therapist_payment_overdue" template.
 */
class TherapistPaymentOverdueNotification extends Notification
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
        $key = EmailTemplateKey::TherapistPaymentOverdue;
        $template = EmailTemplate::forKey($key);

        if ($template === null) {
            return (new MailMessage)
                ->subject($key->defaultSubject())
                ->line($key->label());
        }

        $payable = $this->payment->payable;

        $context = [
            'klient' => (string) ($this->payment->client?->name ?? ''),
            'za_co' => $payable instanceof Payable
                ? PayableTitle::render($payable)['title']
                : 'platbu č. '.$this->payment->number,
            'castka' => number_format((int) $this->payment->amount, 0, ',', ' ').' Kč',
            'email_klienta' => (string) ($this->payment->client?->email ?? ''),
            'sluzba' => $payable instanceof Reservation
                ? (string) ($payable->service?->invoice_title ?? $payable->service?->name ?? '')
                : ($payable instanceof Payable ? PayableTitle::render($payable)['title'] : ''),
            'splatnost' => ($this->payment->due_at?->format('d. m. Y') ?? '').' (po splatnosti!)',
        ];

        return (new MailMessage)
            ->subject($template->subject)
            ->view('emails.rendered', ['html' => EmailTemplateRenderer::render($template, $context)]);
    }
}
