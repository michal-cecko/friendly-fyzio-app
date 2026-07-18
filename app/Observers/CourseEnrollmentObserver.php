<?php

namespace App\Observers;

use App\Enums\CourseEnrollmentStatus;
use App\Models\CourseEnrollment;
use App\Support\Enrollments\PromoteFromWaitlist;

/**
 * A spot freed by a cancelled (or removed) enrollment is immediately offered
 * to the series waitlist — regardless of whether the cancellation came from
 * the public auto-cancel sweep or a manual admin edit.
 */
class CourseEnrollmentObserver
{
    public function updated(CourseEnrollment $enrollment): void
    {
        if ($enrollment->wasChanged('status') && $enrollment->status === CourseEnrollmentStatus::Cancelled) {
            PromoteFromWaitlist::handleAutomatic($enrollment->series);
        }
    }

    public function deleted(CourseEnrollment $enrollment): void
    {
        PromoteFromWaitlist::handleAutomatic($enrollment->series);
    }
}
