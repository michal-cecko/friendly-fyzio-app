<?php

namespace App\Notifications;

use App\Contracts\Payable;
use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\Payment;
use App\Support\EmailTemplateRenderer;
use App\Support\Invoices\PayableTitle;
use App\Support\Pdf\InvoicePdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Client confirmation that a payment was received, rendered from the CMS
 * "payment_received" template. When the payment has an invoice, its PDF is
 * attached (re-rendered from the stored snapshot).
 */
class PaymentReceivedNotification extends Notification
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
        $key = EmailTemplateKey::PaymentReceived;
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
                : 'Platba č. '.$this->payment->number,
            'castka' => number_format((int) $this->payment->amount, 0, ',', ' ').' Kč',
            'datum' => ($this->payment->paid_at ?? now())->format('d. m. Y'),
            'zpusob_platby' => $this->payment->method->getLabel(),
            'cislo_faktury' => $this->payment->invoice?->invoice_number ?? '—',
            'odkaz' => url('/muj-ucet'),
        ];

        $mail = (new MailMessage)
            ->subject($template->subject)
            ->view('emails.rendered', ['html' => EmailTemplateRenderer::render($template, $context)]);

        $invoice = $this->payment->invoice;

        if ($invoice !== null) {
            $mail->attachData(
                app(InvoicePdfRenderer::class)->render($invoice),
                "{$invoice->invoice_number}.pdf",
                ['mime' => 'application/pdf'],
            );
        }

        return $mail;
    }
}
