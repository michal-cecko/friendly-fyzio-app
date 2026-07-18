<?php

namespace App\Support\Enrollments;

use RuntimeException;

/**
 * Thrown when a client tries to cancel a sign-up after its self-cancellation
 * window has closed (or one that is no longer active) — from there on only the
 * clinic can cancel it.
 */
class CancellationWindowClosedException extends RuntimeException
{
    public function __construct(string $message = 'Lhůta pro odhlášení už uplynula. Kontaktujte nás prosím telefonicky.')
    {
        parent::__construct($message);
    }
}
