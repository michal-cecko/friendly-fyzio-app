<?php

namespace App\Support\Enrollments;

use Exception;

/**
 * The offer stopped accepting registrations between render and submit — it
 * filled up, ended, or was switched off. The form re-renders with the fresh
 * state (typically the waitlist).
 */
class OfferClosedException extends Exception {}
