<?php

namespace App\Support\Reservations;

use RuntimeException;

/**
 * Thrown when a deactivated account (e.g. a late-cancel refusal to pay the storno fee)
 * tries to book online. The wizard catches this and shows the customer a clear notice
 * to contact the clinic instead.
 */
class DeactivatedClientException extends RuntimeException
{
    public function __construct(string $message = 'Váš účet byl deaktivován, online rezervace není možná. Kontaktujte prosím kliniku.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
