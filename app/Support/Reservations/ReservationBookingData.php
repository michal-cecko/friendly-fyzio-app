<?php

namespace App\Support\Reservations;

use App\Models\Service;
use App\Models\User;

/**
 * Validated input for {@see CreateReservationFromWizard}. The slot itself is
 * re-resolved inside the action (concurrency safety), so only the chosen
 * service/therapist/date/start and the client's contact details travel here.
 */
final class ReservationBookingData
{
    public function __construct(
        public readonly Service $service,
        public readonly string $therapistId,
        public readonly string $date,        // 'Y-m-d'
        public readonly string $startTime,   // 'H:i'
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $phone,
        public readonly ?string $note = null,
        public readonly bool $newsletter = false,
        public readonly ?User $client = null,
    ) {}

    public function fullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }
}
