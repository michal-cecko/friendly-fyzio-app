<?php

namespace App\Observers;

use App\Models\CourseLesson;
use App\Support\Enrollments\LessonRoster;

/**
 * A newly scheduled lesson immediately gets its presence list, so staff open the
 * Docházka tab to the people who are supposed to be there.
 */
class CourseLessonObserver
{
    public function created(CourseLesson $lesson): void
    {
        LessonRoster::forLesson($lesson);
    }
}
