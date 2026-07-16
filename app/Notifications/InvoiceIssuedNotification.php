<?php

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Support\EmailTemplateRenderer;
use App\Support\Pdf\InvoicePdfData;
use App\Support\Pdf\InvoicePdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The "Odeslat e-mailem" invoice mail: CMS "invoice_issued" template with the
 * invoice PDF (re-rendered from the stored snapshot) attached.
 */
class InvoiceIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = EmailTemplateKey::InvoiceIssued;
        $template = EmailTemplate::forKey($key);

        $pdf = app(InvoicePdfRenderer::class)->render($this->invoice);
        $filename = "{$this->invoice->invoice_number}.pdf";

        if ($template === null) {
            return (new MailMessage)
                ->subject($key->defaultSubject())
                ->line($key->label())
                ->attachData($pdf, $filename, ['mime' => 'application/pdf']);
        }

        $clientName = (string) ($this->invoice->client_snapshot['name'] ?? $this->invoice->client?->name ?? '');

        $context = [
            'jmeno' => Str::of($clientName)->before(' ')->toString() ?: $clientName,
            'cislo_faktury' => (string) $this->invoice->invoice_number,
            'castka' => InvoicePdfData::money((int) $this->invoice->amount),
            'splatnost' => $this->invoice->due_at->format('d. m. Y'),
            'zpusob_platby' => $this->invoice->payment_method?->getLabel() ?? '—',
            // Raw fragment (HtmlString bypasses escaping) for the items-table brick slot.
            'polozky_tabulka' => new HtmlString(
                view('emails.partials.invoice-items-table', ['invoice' => $this->invoice->loadMissing('items')])->render(),
            ),
        ];

        return (new MailMessage)
            ->subject($template->subject)
            ->view('emails.rendered', ['html' => EmailTemplateRenderer::render($template, $context)])
            ->attachData($pdf, $filename, ['mime' => 'application/pdf']);
    }
}
