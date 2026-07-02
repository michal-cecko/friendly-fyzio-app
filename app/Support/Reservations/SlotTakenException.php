<?php

namespace App\Support\Reservations;

use RuntimeException;

/**
 * Thrown when a slot a client tried to book is no longer offerable — either it was
 * taken between selection and submission, or the surrounding bookings changed so the
 * slot stopped being valid. The wizard catches this and sends the user back to pick
 * another time.
 */
class SlotTakenException extends RuntimeException
{
    public function __construct(string $message = 'Vybraný termín už není dostupný.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
