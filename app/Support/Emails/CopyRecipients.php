<?php

namespace App\Support\Emails;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * The extra addresses a staff member put on an outgoing e-mail — an open copy
 * (CC) or a hidden one (BCC, typically accounting or their own archive). Carried
 * as one value so every send path can pass it through unchanged, and applied to
 * the built {@see MailMessage} at the last moment.
 */
readonly class CopyRecipients
{
    /**
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     */
    public function __construct(
        public array $cc = [],
        public array $bcc = [],
    ) {}

    /**
     * Reads the `cc` / `bcc` tag inputs off a submitted action form.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromFormData(array $data): self
    {
        return new self(
            cc: array_values(array_filter((array) ($data['cc'] ?? []))),
            bcc: array_values(array_filter((array) ($data['bcc'] ?? []))),
        );
    }

    public function isEmpty(): bool
    {
        return $this->cc === [] && $this->bcc === [];
    }

    public function apply(MailMessage $mail): MailMessage
    {
        if ($this->cc !== []) {
            $mail->cc($this->cc);
        }

        if ($this->bcc !== []) {
            $mail->bcc($this->bcc);
        }

        return $mail;
    }
}
