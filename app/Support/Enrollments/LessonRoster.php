<?php

namespace App\Support\Enrollments;

use App\Enums\CourseEnrollmentStatus;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSeries;
use App\Models\LessonAttendance;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Keeps `lesson_attendances` a full presence list rather than an exception log:
 * every active enrollment gets a row for every lesson of its série, created when
 * the lesson or the enrollment appears. Staff can then mark someone as not coming
 * (which frees a spot) straight from the lesson's Docházka tab, and the roster is
 * already there when the lesson happens.
 *
 * Rows generated here are written straight to the table — no model events, so the
 * activity log stays reserved for what people actually decide (an excuse, a
 * presence tick), not for bookkeeping.
 */
class LessonRoster
{
    public static function forLesson(CourseLesson $lesson): int
    {
        $enrollmentIds = CourseEnrollment::query()
            ->where('series_id', $lesson->series_id)
            ->where('status', CourseEnrollmentStatus::Active)
            ->pluck('id');

        return self::ensure($enrollmentIds->map(fn (string $enrollmentId): array => [
            'enrollment_id' => $enrollmentId,
            'lesson_id' => $lesson->getKey(),
        ]));
    }

    public static function forEnrollment(CourseEnrollment $enrollment): int
    {
        if ($enrollment->status !== CourseEnrollmentStatus::Active) {
            return 0;
        }

        $lessonIds = CourseLesson::query()
            ->where('series_id', $enrollment->series_id)
            ->pluck('id');

        return self::ensure($lessonIds->map(fn (string $lessonId): array => [
            'enrollment_id' => $enrollment->getKey(),
            'lesson_id' => $lessonId,
        ]));
    }

    public static function forSeries(CourseSeries $series): int
    {
        $enrollmentIds = $series->activeTakers()->pluck('id');
        $lessonIds = $series->lessons()->pluck('id');

        $pairs = $enrollmentIds->crossJoin($lessonIds)
            ->map(fn (array $pair): array => [
                'enrollment_id' => $pair[0],
                'lesson_id' => $pair[1],
            ]);

        return self::ensure($pairs);
    }

    /**
     * Inserts the pairs that have no row yet. Existing rows are never touched — an
     * excuse or a presence tick must survive a later roster pass.
     *
     * @param  Collection<int, array{enrollment_id: string, lesson_id: string}>  $pairs
     */
    private static function ensure(Collection $pairs): int
    {
        if ($pairs->isEmpty()) {
            return 0;
        }

        $existing = LessonAttendance::query()
            ->whereIn('enrollment_id', $pairs->pluck('enrollment_id')->unique())
            ->whereIn('lesson_id', $pairs->pluck('lesson_id')->unique())
            ->get(['enrollment_id', 'lesson_id'])
            ->map(fn (LessonAttendance $row): string => self::key($row->enrollment_id, $row->lesson_id))
            ->all();

        $missing = $pairs
            ->reject(fn (array $pair): bool => in_array(self::key($pair['enrollment_id'], $pair['lesson_id']), $existing, true))
            ->values();

        if ($missing->isEmpty()) {
            return 0;
        }

        $now = now();

        $missing
            ->map(fn (array $pair): array => [
                'id' => (string) Str::uuid(),
                'enrollment_id' => $pair['enrollment_id'],
                'lesson_id' => $pair['lesson_id'],
                'attended' => false,
                'cancelled_at' => null,
                'token_generated' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->chunk(500)
            ->each(fn (Collection $chunk) => LessonAttendance::insertOrIgnore($chunk->all()));

        return $missing->count();
    }

    private static function key(string $enrollmentId, string $lessonId): string
    {
        return $enrollmentId.'|'.$lessonId;
    }
}
