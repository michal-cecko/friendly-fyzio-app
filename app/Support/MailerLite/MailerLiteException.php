<?php

namespace App\Support\MailerLite;

use RuntimeException;

/**
 * Raised when a MailerLite API call fails (missing API key, an error response,
 * or an unexpected status from the Connect API).
 */
class MailerLiteException extends RuntimeException {}
