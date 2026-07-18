<?php

namespace App\Support\Substitutes;

use RuntimeException;

/**
 * A substitute action (excuse or redeem) that the rules don't allow — the
 * message is customer-facing Czech and safe to show in the client zone.
 */
class SubstituteException extends RuntimeException {}
