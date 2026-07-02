<?php

namespace App\Support\Instagram;

use RuntimeException;

/**
 * Raised when an Instagram API call fails or a connection cannot be synced
 * (missing credentials, expired token, or an error response from Meta).
 */
class InstagramException extends RuntimeException {}
