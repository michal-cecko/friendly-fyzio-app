<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Reservation $reservation,
        public string $type = 'created',
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
        $reservation = $this->reservation->loadMissing('service', 'therapist.user', 'client');
        $when = $reservation->reservation_date?->format('d.m.Y').' '.$reservation->start_time;
        $service = $reservation->service?->name ?? 'služba';
        $therapist = $reservation->therapist?->user?->name;

        $message = (new MailMessage)->greeting('Dobrý den,');

        if ($this->type === 'cancelled') {
            return $message
                ->subject('Vaše rezervace byla zrušena')
                ->line("Vaše rezervace služby „{$service}“ dne {$when} byla zrušena.")
                ->when(filled($reservation->cancellation_reason), fn (MailMessage $mail) => $mail->line("Důvod: {$reservation->cancellation_reason}"))
                ->line('V případě dotazů nás neváhejte kontaktovat.');
        }

        if ($this->type === 'reminder') {
            return $message
                ->subject('Připomenutí rezervace')
                ->line("Připomínáme vaši rezervaci služby „{$service}“.")
                ->line("Termín: {$when}")
                ->when($therapist, fn (MailMessage $mail) => $mail->line("Terapeut: {$therapist}"))
                ->line('Těšíme se na vaši návštěvu.');
        }

        if ($this->type === 'updated') {
            return $message
                ->subject('Změna vaší rezervace')
                ->line("Vaše rezervace služby „{$service}“ byla upravena.")
                ->line("Nový termín: {$when}")
                ->when($therapist, fn (MailMessage $mail) => $mail->line("Terapeut: {$therapist}"))
                ->line('Děkujeme za pochopení.');
        }

        return $message
            ->subject('Potvrzení rezervace')
            ->line("Vaše rezervace služby „{$service}“ byla vytvořena.")
            ->line("Termín: {$when}")
            ->when($therapist, fn (MailMessage $mail) => $mail->line("Terapeut: {$therapist}"))
            ->line('Těšíme se na vaši návštěvu.');
    }
}
