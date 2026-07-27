<?php

namespace App\Support\Reservations;

use App\Enums\EmailTemplateKey;
use App\Filament\Support\Actions\ScheduleChangeNotificationPrompt;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use App\Notifications\TherapistReservationTemplateNotification;
use App\Support\Emails\MessageBlock;
use App\Support\Emails\SentEmailReceipt;

/**
 * E-mails the client — and the assigned therapist — that a reservation's termín
 * (date, time, room or therapist) changed. The old values arrive as $snapshot
 * ({{ puvodni_* }} tokens) captured before the edit was saved by
 * {@see ReservationChangeSnapshot}, and the optional staff message renders into the
 * templates' {{ zprava }} block ({@see MessageBlock}).
 *
 * Shared by the reservation edit page and the calendar edit modal, which both prompt
 * for it after a schedule change ({@see ScheduleChangeNotificationPrompt}).
 */
class NotifyReservationChange
{
    /**
     * @param  array<string, string>  $snapshot  puvodni_* tokens from ReservationChangeSnapshot.
     * @return int Number of recipients (client + therapist) e-mailed.
     */
    public function __invoke(Reservation $reservation, array $snapshot = [], ?string $reason = null): int
    {
        $tokens = [
            'duvod' => (string) ($reason ?? ''),
            'zprava' => MessageBlock::render($reason),
            ...$snapshot,
        ];

        $notifiedClient = filled($reservation->client?->email);
        $notifiedTherapist = $reservation->therapist?->user !== null;

        if ($notifiedClient) {
            $reservation->client?->notify(new ReservationTemplateNotification(
                $reservation,
                EmailTemplateKey::ReservationChanged,
                $tokens,
            ));
        }

        if ($notifiedTherapist) {
            $reservation->therapist?->user?->notify(new TherapistReservationTemplateNotification(
                $reservation,
                EmailTemplateKey::TherapistReservationChanged,
                $tokens,
            ));
        }

        $count = (int) $notifiedClient + (int) $notifiedTherapist;

        if ($count > 0) {
            SentEmailReceipt::forCurrentUser('Změna rezervace', $count);
        }

        return $count;
    }
}
