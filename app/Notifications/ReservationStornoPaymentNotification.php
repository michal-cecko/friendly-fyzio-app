<?php

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\EmailTemplateRenderer;
use App\Support\Payments\PaymentNote;
use App\Support\Payments\QrPlatba;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The late-cancel storno-fee e-mail: the CMS "storno payment" template rendered with
 * the reservation's tokens plus the payment specifics — amount, IBAN, variable symbol,
 * and an embedded Czech QR-Platba image (data-URI).
 */
class ReservationStornoPaymentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Reservation $reservation,
        public Payment $payment,
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
        $key = EmailTemplateKey::ReservationStornoPayment;
        $template = EmailTemplate::forKey($key);

        if ($template === null) {
            return (new MailMessage)
                ->subject($key->defaultSubject())
                ->line($key->label());
        }

        $context = [
            ...PaymentNote::context($this->payment),
            'iban' => Settings::iban(),
            'qr' => QrPlatba::dataUri($this->payment),
        ];

        $html = EmailTemplateRenderer::render($template, $context);

        return (new MailMessage)
            ->subject($template->subject)
            ->view('emails.rendered', ['html' => $html]);
    }
}
