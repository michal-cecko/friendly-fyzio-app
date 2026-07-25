<?php

namespace App\Support\Enrollments;

/**
 * Tally of an {@see OfferSpotToEntry::inviteMany()} run, so an admin action can
 * report "offered X, Y didn't fit, Z had no e-mail".
 */
final readonly class InviteSummary
{
    public function __construct(
        public int $offered = 0,
        public int $skippedFull = 0,
        public int $skippedDeadEnd = 0,
        public int $skippedNoEmail = 0,
    ) {}

    public function total(): int
    {
        return $this->offered + $this->skippedFull + $this->skippedDeadEnd + $this->skippedNoEmail;
    }
}
