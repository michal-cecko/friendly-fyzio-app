<?php

namespace App\Support\Substitutes;

use App\Enums\CourseEnrollmentStatus;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Manual substitute override: a lecturer/admin moves a client from one lesson of
 * their own série into any target lesson, deliberately bypassing the automatic
 * substitution rules a client-zone redeem must satisfy ({@see RedeemToken}) —
 * the {@see SubstituteRule} pairing, token limits/expiry, and capacity.
 *
 * Unlike a self-service excuse the source cancellation mints no token and does
 * not count against the client's `max_substitutions`: this placement *is* the
 * make-up, so no further one is owed. The move is expressed purely as two
 * attendance rows (a cancelled source row + a target row), both recorded against
 * the client's own source enrollment — rosters read `lesson.attendances`, never
 * assuming `enrollment.series_id === lesson.series_id`.
 */
class MoveClientToLesson
{
    public function __invoke(User $client, Lesson $target, Lesson $source): LessonAttendance
    {
        $lock = Cache::lock("lesson:{$target->getKey()}", 10);

        /** @var LessonAttendance */
        return $lock->block(5, fn (): LessonAttendance => DB::transaction(
            fn (): LessonAttendance => $this->persist($client, $target, $source),
        ));
    }

    protected function persist(User $client, Lesson $target, Lesson $source): LessonAttendance
    {
        $enrollment = $this->activeEnrollment($client, $source);

        if ($this->alreadyBookedInTarget($client, $target)) {
            throw new SubstituteException('Klient už je do této lekce přihlášen.');
        }

        $excused = LessonAttendance::updateOrCreate(
            ['client_id' => $client->getKey(), 'lesson_id' => $source->getKey()],
            [
                'enrollment_id' => $enrollment->getKey(),
                'attended' => false,
                'cancelled_at' => now(),
                'excused_by_id' => auth()->id(),
                'token_generated' => false,
            ],
        );

        $replacement = LessonAttendance::create([
            'client_id' => $client->getKey(),
            'enrollment_id' => $enrollment->getKey(),
            'lesson_id' => $target->getKey(),
            'attended' => true,
        ]);

        $excused->update(['replacement_attendance_id' => $replacement->getKey()]);

        return $replacement;
    }

    protected function activeEnrollment(User $client, Lesson $source): CourseEnrollment
    {
        $enrollment = CourseEnrollment::query()
            ->where('client_id', $client->getKey())
            ->where('series_id', $source->series_id)
            ->where('status', CourseEnrollmentStatus::Active)
            ->first();

        if ($enrollment === null) {
            throw new SubstituteException('Klient nemá aktivní přihlášku do série zdrojové lekce.');
        }

        return $enrollment;
    }

    protected function alreadyBookedInTarget(User $client, Lesson $target): bool
    {
        return LessonAttendance::query()
            ->where('lesson_id', $target->getKey())
            ->where('client_id', $client->getKey())
            ->whereNull('cancelled_at')
            ->exists();
    }
}
