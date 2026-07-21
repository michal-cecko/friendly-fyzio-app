<?php

namespace App\Contracts;

use App\Enums\EmailTemplateKey;
use App\Filament\Support\Actions\SendEmailAction;

/**
 * A record an admin can e-mail about from its detail page via the shared
 * {@see SendEmailAction}. Implementers expose their
 * default recipient, the CMS templates worth (re)sending for this record type, and a
 * dispatcher that resolves the correct recipient + token context internally.
 */
interface Emailable
{
    /**
     * Default "To" address prefilled into the custom composer (usually the client's).
     */
    public function emailRecipientAddress(): ?string;

    /**
     * Optional display name for the default recipient.
     */
    public function emailRecipientName(): ?string;

    /**
     * Template picker options, grouped by audience. Shape: ['Klient' => [key->value => label], …].
     * An empty array means this record has no resendable templates — the action opens
     * straight into custom-compose mode.
     *
     * @return array<string, array<string, string>>
     */
    public function emailTemplateGroups(): array;

    /**
     * Dispatch the chosen template e-mail, resolving the recipient, token context and
     * notification for this record type.
     */
    public function sendTemplateEmail(EmailTemplateKey $key): void;
}
