<?php

namespace App\Support\Emails;

use App\Support\Enrollments\NotifyScheduleChange;
use App\Support\Reservations\NotifyReservationChange;
use Illuminate\Support\HtmlString;

/**
 * Wraps the optional free-text message staff attach to a schedule-change e-mail in
 * an email-safe accented block, so it stands out from the surrounding copy. Shared
 * by the lesson ({@see NotifyScheduleChange}) and reservation
 * ({@see NotifyReservationChange}) change e-mails, rendered
 * into the templates' {{ zprava }} token.
 *
 * Resolves to an empty string when no message was entered, leaving no residue in the
 * rendered e-mail.
 */
class MessageBlock
{
    public static function render(?string $reason): string|HtmlString
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            return '';
        }

        return new HtmlString(
            '<div style="margin:16px 0 0;padding:12px 16px;background-color:#FFF8FA;border-left:3px solid #ED86A3;border-radius:6px;'
            ."font-family:'Open Sans',Arial,sans-serif;font-size:14px;line-height:1.6;color:#666666;\">"
            .nl2br(e($reason))
            .'</div>',
        );
    }
}
