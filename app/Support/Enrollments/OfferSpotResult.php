<?php

namespace App\Support\Enrollments;

/**
 * Outcome of offering a spot to one waitlist entry.
 */
enum OfferSpotResult
{
    /** A sign-up + payment request was created and the offer e-mail sent. */
    case Created;

    /** A dead-end entry (deactivated client / already signed up / no e-mail) — consumed, skipped. */
    case Skipped;

    /** An unexpected error was reported; the caller should stop. */
    case Failed;
}
