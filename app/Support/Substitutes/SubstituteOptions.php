<?php

namespace App\Support\Substitutes;

use App\Enums\CourseEnrollmentStatus;
use App\Models\CourseLesson;
use App\Models\LessonAttendance;
use App\Models\SubstituteRule;
use App\Models\SubstituteToken;
use Illuminate\Support\Collection;

/**
 * The lessons a substitute token may be redeemed against: upcoming lessons of
 * the courses configured as substitute targets for the token's source course
 * (see SubstituteRule), that still have a free spot. Per the docs these
 * substitute places are never shown publicly — only inside the client zone.
 */
class SubstituteOptions
{
    /**
     * @return Collection<int, CourseLesson>
     */
    public function forToken(SubstituteToken $token): Collection
    {
        $sourceCourseId = $token->sourceLesson?->series?->course_id;

        if ($sourceCourseId === null) {
            return new Collection;
        }

        $targetCourseIds = SubstituteRule::query()
            ->where('source_course_id', $sourceCourseId)
            ->pluck('target_course_id');

        if ($targetCourseIds->isEmpty()) {
            return new Collection;
        }

        // Lessons the client already attends (own enrollment or a previously
        // redeemed substitute) must not be offered again — otherwise two tokens
        // could be burnt on the same lesson.
        $alreadyBookedLessonIds = LessonAttendance::query()
            ->whereNull('cancelled_at')
            ->whereHas('enrollment', fn ($query) => $query->where('client_id', $token->client_id))
            ->pluck('lesson_id');

        return CourseLesson::query()
            ->whereHas('series', fn ($query) => $query->whereIn('course_id', $targetCourseIds))
            ->whereNotIn('id', $alreadyBookedLessonIds)
            ->whereDate('lesson_date', '>=', today())
            ->with(['series.course', 'room'])
            ->orderBy('lesson_date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (CourseLesson $lesson): bool => $lesson->lesson_date
                ->copy()
                ->setTimeFromTimeString((string) $lesson->start_time)
                ->isFuture())
            ->filter(fn (CourseLesson $lesson): bool => $this->freeSpots($lesson) > 0)
            ->values();
    }

    /**
     * Free places on a single lesson: the series capacity, minus its active
     * enrollments, plus anyone excused from this particular lesson, minus the
     * substitutes already booked in.
     */
    public function freeSpots(CourseLesson $lesson): int
    {
        $series = $lesson->series;

        if ($series === null) {
            return 0;
        }

        $enrolled = $series->enrollments()->where('status', CourseEnrollmentStatus::Active)->count();

        $excused = LessonAttendance::query()
            ->where('lesson_id', $lesson->getKey())
            ->whereNotNull('cancelled_at')
            ->count();

        $substitutesIn = SubstituteToken::query()
            ->where('used_for_lesson_id', $lesson->getKey())
            ->count();

        return max(0, (int) $series->capacity - $enrolled + $excused - $substitutesIn);
    }
}
