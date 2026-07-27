<?php

namespace App\Observers;

use App\Models\Lesson;
use App\Support\Enrollments\LessonRoster;

/**
 * A newly scheduled lesson of a course série immediately gets its presence list,
 * so staff open the Docházka tab to the people who are supposed to be there.
 * A standalone lesson has no roster — its attendees book individually.
 */
class LessonObserver
{
    public function created(Lesson $lesson): void
    {
        if (! $lesson->isPartOfSeries()) {
            return;
        }

        LessonRoster::forLesson($lesson);
    }
}
