<?php

namespace App\Support\Emails;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Reports the outcome of an admin-triggered send back to the admin who
 * triggered it: a toast right away, plus a database notification so the receipt
 * survives navigating away from the page the send was started from.
 *
 * Every e-mail action in the panel finishes through here, so the three outcomes
 * — suppressed before launch, nothing to send, actually sent — read the same
 * everywhere.
 */
class SentEmailReceipt
{
    /**
     * For a send that finished inside the request: toast the acting admin and
     * leave them a durable copy.
     *
     * @param  int  $sent  How many recipients were actually notified.
     * @param  string  $what  What went out, e.g. "Pozvánka" — used in every message.
     */
    public static function report(int $sent, string $what): void
    {
        // Before launch only administrators actually receive mail, so staff must
        // not be told a message went out when it did not.
        if (static::isSuppressed()) {
            Notification::make()
                ->title($what.' nebyl odeslán.')
                ->body('Odesílání e-mailů klientům a terapeutům je před spuštěním pozastaveno.')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        if ($sent < 1) {
            Notification::make()
                ->title('Nebyl vybrán žádný platný příjemce.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Odesláno')
            ->body(static::summary($what, $sent))
            ->success()
            ->send();

        static::recordForSender(Auth::user(), $what, $sent);
    }

    /**
     * Durable receipt only, for actions that already say their own piece in a
     * toast worded around what they did ("Faktura byla odeslána", "Rezervace
     * potvrzena"). Leaves that toast alone and just files the copy.
     */
    public static function forCurrentUser(string $what, int $sent = 1): void
    {
        $sender = Auth::user();

        static::recordForSender($sender, $what, $sent);

        // The calling action has already toasted, so the bell must not announce
        // the same send again on its next poll.
        static::markLatestAsToasted($sender);
    }

    /**
     * For a send that finished on the queue, where there is no session to toast:
     * only the durable copy, addressed to the admin who started it.
     *
     * Scheduled commands pass no sender and so report to nobody.
     */
    public static function recordForSender(?User $sender, string $what, int $sent): void
    {
        if (! $sender instanceof User) {
            return;
        }

        if (static::isSuppressed()) {
            Notification::make()
                ->title($what.' nebyl odeslán')
                ->body('Odesílání e-mailů klientům a terapeutům je před spuštěním pozastaveno.')
                ->warning()
                ->sendToDatabase($sender);

            return;
        }

        Notification::make()
            ->title($sent > 0 ? 'E-mail odeslán' : 'Nebyl odeslán žádný e-mail')
            ->body(static::summary($what, $sent))
            ->{$sent > 0 ? 'success' : 'warning'}()
            ->sendToDatabase($sender);
    }

    /**
     * `sendToDatabase()` hands back the notification builder rather than the
     * stored row, so the row just written is the recipient's newest.
     */
    private static function markLatestAsToasted(?User $sender): void
    {
        $sender?->notifications()
            ->latest()
            ->limit(1)
            ->update(['toasted_at' => now()]);
    }

    private static function isSuppressed(): bool
    {
        return (bool) config('mail.suppress_non_admin');
    }

    private static function summary(string $what, int $sent): string
    {
        return $what.' — '.$sent.' '.match (true) {
            $sent === 1 => 'příjemce',
            $sent <= 4 => 'příjemci',
            default => 'příjemců',
        };
    }
}
