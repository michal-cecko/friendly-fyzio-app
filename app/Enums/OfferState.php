<?php

namespace App\Enums;

/**
 * Public display/registration state of an enrollable offer (course series,
 * one-time lesson, workshop). Drives the archive card badge, the detail-page
 * badge and which registration UI is shown:
 *
 * - Open      → registration form (mid-series pro-rating applies once started)
 * - Full      → waitlist sign-up
 * - Preparing → "notify me" form, no registration
 * - Inactive  → muted informational display, no registration (past / not listed)
 */
enum OfferState: string
{
    case Open = 'open';
    case Full = 'full';
    case Preparing = 'preparing';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Přihlašujeme',
            self::Full => 'Obsazeno',
            self::Preparing => 'Připravujeme',
            self::Inactive => 'Momentálně nepřihlašujeme',
        };
    }

    public function acceptsRegistrations(): bool
    {
        return $this === self::Open;
    }

    public function acceptsWaitlist(): bool
    {
        return $this === self::Full;
    }
}
