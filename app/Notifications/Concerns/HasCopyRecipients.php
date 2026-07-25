<?php

namespace App\Notifications\Concerns;

use App\Support\Emails\CopyRecipients;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * For notifications a staff member can send by hand: applies the CC/BCC they
 * typed into the send action. Automatic sends leave `$copies` null and behave
 * exactly as before.
 *
 * Implementers declare `public ?CopyRecipients $copies` (usually the last
 * constructor argument) and pipe their built message through
 * {@see applyCopies()}.
 */
trait HasCopyRecipients
{
    protected function applyCopies(MailMessage $mail): MailMessage
    {
        return $this->copies?->apply($mail) ?? $mail;
    }
}
