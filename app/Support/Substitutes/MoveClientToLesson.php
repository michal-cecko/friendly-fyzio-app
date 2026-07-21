<?php

namespace App\Support\Substitutes;

use App\Enums\CourseEnrollmentStatus;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
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
    public function __invoke(User $client, CourseLesson $target, CourseLesson $source): LessonAttendance
    {
        $lock = Cache::lock("substitute:lesson:{$target->getKey()}", 10);

        /** @var LessonAttendance */
        return $lock->block(5, fn (): LessonAttendance => DB::transaction(
            fn (): LessonAttendance => $this->persist($client, $target, $source),
        ));
    }

    protected function persist(User $client, CourseLesson $target, CourseLesson $source): LessonAttendance
    {
        $enrollment = $this->activeEnrollment($client, $source);

        if ($this->alreadyBookedInTarget($client, $target)) {
            throw new SubstituteException('Klient už je do této lekce přihlášen.');
        }

        LessonAttendance::updateOrCreate(
            ['enrollment_id' => $enrollment->getKey(), 'lesson_id' => $source->getKey()],
            ['attended' => false, 'cancelled_at' => now(), 'token_generated' => false],
        );

        return LessonAttendance::create([
            'enrollment_id' => $enrollment->getKey(),
            'lesson_id' => $target->getKey(),
            'attended' => false,
        ]);
    }

    protected function activeEnrollment(User $client, CourseLesson $source): CourseEnrollment
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

    protected function alreadyBookedInTarget(User $client, CourseLesson $target): bool
    {
        return LessonAttendance::query()
            ->where('lesson_id', $target->getKey())
            ->whereNull('cancelled_at')
            ->whereHas('enrollment', fn ($query) => $query->where('client_id', $client->getKey()))
            ->exists();
    }
}
