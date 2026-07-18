<?php

namespace App\Support\Substitutes;

use App\Enums\EmailTemplateKey;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonAttendance;
use App\Models\SubstituteToken;
use App\Notifications\SubstituteTokenNotification;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Excusing a client from a single lesson of their course ("odhlásit se z lekce").
 *
 * A timely excuse — at least the course's `early_cancel_hours` before the lesson
 * starts, and while the client is still under the course's `max_substitutions`
 * limit — mints a substitute token they can redeem in an allowed parallel course.
 * A late (or over-limit) excuse is still recorded, but generates no token: the
 * rules are the same ones the docs describe (FSS §6.4).
 */
class ExcuseFromLesson
{
    public function __invoke(CourseEnrollment $enrollment, CourseLesson $lesson): ?SubstituteToken
    {
        $this->guard($enrollment, $lesson);

        $course = $enrollment->series?->course;
        $timely = $this->isTimely($lesson, (int) ($course?->early_cancel_hours ?? 0));
        $underLimit = $this->tokensUsedSoFar($enrollment) < (int) ($course?->max_substitutions ?? 0);
        $generatesToken = $timely && $underLimit;

        $token = DB::transaction(function () use ($enrollment, $lesson, $generatesToken): ?SubstituteToken {
            LessonAttendance::updateOrCreate(
                ['enrollment_id' => $enrollment->getKey(), 'lesson_id' => $lesson->getKey()],
                ['attended' => false, 'cancelled_at' => now(), 'token_generated' => $generatesToken],
            );

            if (! $generatesToken) {
                return null;
            }

            return SubstituteToken::create([
                'client_id' => $enrollment->client_id,
                'source_lesson_id' => $lesson->getKey(),
                'expires_at' => now()->addDays(Settings::substituteTokenValidityDays()),
            ]);
        });

        if ($token !== null) {
            $enrollment->client?->notify(new SubstituteTokenNotification(
                EmailTemplateKey::SubstituteTokenGenerated,
                [
                    'jmeno' => str((string) $enrollment->client?->name)->before(' ')->toString(),
                    'kurz' => (string) ($enrollment->series?->course?->name ?? ''),
                    'lekce' => $this->lessonLabel($lesson),
                    'platnost' => $token->expires_at->format('j. n. Y'),
                    'odkaz' => url('/muj-ucet/nahrady'),
                ],
            ));
        }

        return $token;
    }

    /**
     * Whether excusing from this lesson right now would still mint a token —
     * used by the UI to label the button honestly before the client clicks.
     */
    public function wouldGenerateToken(CourseEnrollment $enrollment, CourseLesson $lesson): bool
    {
        $course = $enrollment->series?->course;

        return $this->isTimely($lesson, (int) ($course?->early_cancel_hours ?? 0))
            && $this->tokensUsedSoFar($enrollment) < (int) ($course?->max_substitutions ?? 0);
    }

    protected function guard(CourseEnrollment $enrollment, CourseLesson $lesson): void
    {
        if ($lesson->series_id !== $enrollment->series_id) {
            throw new SubstituteException('Tato lekce nepatří do vašeho kurzu.');
        }

        if ($this->lessonStart($lesson)->isPast()) {
            throw new SubstituteException('Z proběhlé lekce se odhlásit nelze.');
        }

        $alreadyExcused = LessonAttendance::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('lesson_id', $lesson->getKey())
            ->whereNotNull('cancelled_at')
            ->exists();

        if ($alreadyExcused) {
            throw new SubstituteException('Z této lekce už jste odhlášeni.');
        }
    }

    protected function isTimely(CourseLesson $lesson, int $earlyCancelHours): bool
    {
        return now()->lessThanOrEqualTo($this->lessonStart($lesson)->subHours($earlyCancelHours));
    }

    protected function tokensUsedSoFar(CourseEnrollment $enrollment): int
    {
        return LessonAttendance::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('token_generated', true)
            ->count();
    }

    protected function lessonStart(CourseLesson $lesson): Carbon
    {
        return $lesson->lesson_date->copy()->setTimeFromTimeString((string) $lesson->start_time);
    }

    protected function lessonLabel(CourseLesson $lesson): string
    {
        return $this->lessonStart($lesson)->format('j. n. Y · H:i');
    }
}
