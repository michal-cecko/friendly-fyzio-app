<?php

namespace App\Notifications;

use App\Contracts\Payable;
use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\Payment;
use App\Support\EmailTemplateRenderer;
use App\Support\Invoices\PayableTitle;
use App\Support\Payments\PaymentNote;
use App\Support\Payments\QrPlatba;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Client dunning notice for a payment past its due date — the CMS
 * "payment_overdue" template with the payment box (IBAN, VS, QR, splatnost).
 */
class PaymentOverdueNotification extends Notification
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
        $key = EmailTemplateKey::PaymentOverdue;
        $template = EmailTemplate::forKey($key);

        if ($template === null) {
            return (new MailMessage)
                ->subject($key->defaultSubject())
                ->line($key->label());
        }

        $payable = $this->payment->payable;
        $clientName = (string) ($this->payment->client?->name ?? '');

        $context = [
            'jmeno' => Str::of($clientName)->before(' ')->toString() ?: $clientName,
            'za_co' => $payable instanceof Payable
                ? PayableTitle::render($payable)['title']
                : 'platbu č. '.$this->payment->number,
            'castka' => number_format((int) $this->payment->amount, 0, ',', ' '),
            'iban' => Settings::iban(),
            'vs' => (string) $this->payment->variable_symbol,
            'zprava' => PaymentNote::render($this->payment),
            'splatnost' => $this->payment->due_at?->format('d. m. Y') ?? '',
            'qr' => QrPlatba::dataUri($this->payment),
        ];

        return (new MailMessage)
            ->subject($template->subject)
            ->view('emails.rendered', ['html' => EmailTemplateRenderer::render($template, $context)]);
    }
}
