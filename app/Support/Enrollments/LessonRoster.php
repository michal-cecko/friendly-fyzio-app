<?php

namespace App\Support\Enrollments;

use App\Enums\CourseEnrollmentStatus;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Keeps `lesson_attendances` a full presence list rather than an exception log:
 * every active enrollment gets a row for every lesson of its série, created when
 * the lesson or the enrollment appears. Rows start out present — being on the
 * list is what being expected means. Staff can then mark someone as not coming
 * (which frees a spot) straight from the lesson's Docházka tab, and the roster is
 * already there when the lesson happens.
 *
 * The list is keyed on the client, not the enrollment — somebody who bought a
 * single seat is on it too, seated by {@see App\Observers\LessonBookingObserver}
 * rather than here.
 *
 * Rows generated here are written straight to the table — no model events, so the
 * activity log stays reserved for what people actually decide (an excuse, a
 * presence tick), not for bookkeeping.
 */
class LessonRoster
{
    public static function forLesson(Lesson $lesson): int
    {
        $enrollments = CourseEnrollment::query()
            ->where('series_id', $lesson->series_id)
            ->where('status', CourseEnrollmentStatus::Active)
            ->get(['id', 'client_id']);

        return self::ensure($enrollments->map(fn (CourseEnrollment $enrollment): array => [
            'client_id' => $enrollment->client_id,
            'enrollment_id' => $enrollment->getKey(),
            'lesson_id' => $lesson->getKey(),
        ]));
    }

    public static function forEnrollment(CourseEnrollment $enrollment): int
    {
        if ($enrollment->status !== CourseEnrollmentStatus::Active) {
            return 0;
        }

        $lessonIds = Lesson::query()
            ->where('series_id', $enrollment->series_id)
            ->pluck('id');

        return self::ensure($lessonIds->map(fn (string $lessonId): array => [
            'client_id' => $enrollment->client_id,
            'enrollment_id' => $enrollment->getKey(),
            'lesson_id' => $lessonId,
        ]));
    }

    public static function forSeries(CourseSeries $series): int
    {
        $enrollments = $series->activeTakers()->get(['id', 'client_id']);
        $lessonIds = $series->lessons()->pluck('id');

        $pairs = $enrollments->crossJoin($lessonIds)
            ->map(fn (array $pair): array => [
                'client_id' => $pair[0]->client_id,
                'enrollment_id' => $pair[0]->getKey(),
                'lesson_id' => $pair[1],
            ]);

        return self::ensure($pairs);
    }

    /**
     * Inserts the rows that have no seat yet. Existing rows are never touched — an
     * excuse or a presence tick must survive a later roster pass, and a client
     * already seated by a drop-in booking keeps that seat rather than gaining a
     * second one.
     *
     * @param  Collection<int, array{client_id: string, enrollment_id: string, lesson_id: string}>  $seats
     */
    private static function ensure(Collection $seats): int
    {
        if ($seats->isEmpty()) {
            return 0;
        }

        $existing = LessonAttendance::query()
            ->whereIn('client_id', $seats->pluck('client_id')->unique())
            ->whereIn('lesson_id', $seats->pluck('lesson_id')->unique())
            ->get(['client_id', 'lesson_id'])
            ->map(fn (LessonAttendance $row): string => self::key($row->client_id, $row->lesson_id))
            ->all();

        $missing = $seats
            ->reject(fn (array $seat): bool => in_array(self::key($seat['client_id'], $seat['lesson_id']), $existing, true))
            ->values();

        if ($missing->isEmpty()) {
            return 0;
        }

        $now = now();

        $missing
            ->map(fn (array $seat): array => [
                'id' => (string) Str::uuid(),
                'client_id' => $seat['client_id'],
                'enrollment_id' => $seat['enrollment_id'],
                'booking_id' => null,
                'lesson_id' => $seat['lesson_id'],
                'attended' => true,
                'cancelled_at' => null,
                'token_generated' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->chunk(500)
            ->each(fn (Collection $chunk) => LessonAttendance::insertOrIgnore($chunk->all()));

        return $missing->count();
    }

    private static function key(string $clientId, string $lessonId): string
    {
        return $clientId.'|'.$lessonId;
    }
}
