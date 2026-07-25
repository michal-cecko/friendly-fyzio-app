<?php

namespace App\Observers;

use App\Enums\CourseEnrollmentStatus;
use App\Models\CourseEnrollment;
use App\Support\Enrollments\LessonRoster;
use App\Support\Enrollments\PromoteFromWaitlist;

/**
 * A spot freed by a cancelled (or removed) enrollment is immediately offered
 * to the series waitlist — regardless of whether the cancellation came from
 * the public auto-cancel sweep or a manual admin edit.
 *
 * The same hooks keep the lesson presence lists in step ({@see LessonRoster}):
 * joining the série fills them in. Cancelling leaves the rows alone — they stop
 * counting the moment the enrollment is no longer active, and keeping them
 * preserves the history of who was excused from what.
 */
class CourseEnrollmentObserver
{
    public function created(CourseEnrollment $enrollment): void
    {
        LessonRoster::forEnrollment($enrollment);
    }

    public function updated(CourseEnrollment $enrollment): void
    {
        if (! $enrollment->wasChanged('status')) {
            return;
        }

        if ($enrollment->status === CourseEnrollmentStatus::Cancelled) {
            PromoteFromWaitlist::handleAutomatic($enrollment->series);

            return;
        }

        if ($enrollment->status === CourseEnrollmentStatus::Active) {
            LessonRoster::forEnrollment($enrollment);
        }
    }

    public function deleted(CourseEnrollment $enrollment): void
    {
        PromoteFromWaitlist::handleAutomatic($enrollment->series);
    }
}
