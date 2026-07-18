<?php

namespace App\Support\Enrollments;

use Exception;

/**
 * The client already holds an active sign-up for this offer — duplicate
 * submissions never create a second spot (or a second payment request).
 */
class AlreadySignedUpException extends Exception {}
