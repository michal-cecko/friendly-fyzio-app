<?php

namespace App\Support\Enrollments;

use App\Models\User;

/**
 * A validated public sign-up submission (course series, one-time lesson or
 * workshop form). `client` carries the authenticated user when there is one;
 * guests are resolved/created by e-mail.
 */
final readonly class EnrollmentData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?string $note = null,
        public ?User $client = null,
    ) {}
}
