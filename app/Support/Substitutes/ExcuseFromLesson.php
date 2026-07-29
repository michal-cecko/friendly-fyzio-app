<?php

namespace App\Support\Substitutes;

use App\Enums\EmailTemplateKey;
use App\Enums\LessonExcuseReason;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\SubstituteToken;
use App\Models\User;
use App\Notifications\SubstituteTokenNotification;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Excusing a client from a single lesson of their course ("odhlásit se z lekce").
 *
 * A timely excuse — at least the course's `early_cancel_hours` before the lesson
 * starts, and while the client is still under the série's `max_substitutions`
 * limit — mints a substitute token they can redeem in an allowed parallel course.
 * A late (or over-limit) excuse is still recorded, but generates no token: the
 * rules are the same ones the docs describe (FSS §6.4).
 *
 * Staff excusing somebody from the lesson's Docházka tab go through the same
 * rules, but the panel gives them three things a client never gets: recording an
 * absence after the lesson has happened (`$allowPast`), granting a náhrada the
 * rules would refuse (`$overrideRules`), and noting down why (`$reason`,
 * `$note`). The defaults keep the client-zone flow unchanged.
 */
class ExcuseFromLesson
{
    public function __invoke(
        CourseEnrollment $enrollment,
        Lesson $lesson,
        bool $allowToken = true,
        bool $notifyClient = true,
        ?LessonExcuseReason $reason = null,
        ?string $note = null,
        ?User $actor = null,
        bool $allowPast = false,
        bool $overrideRules = false,
    ): ?SubstituteToken {
        $this->guard($enrollment, $lesson, $allowPast);

        $generatesToken = $allowToken
            && ($overrideRules || $this->wouldGenerateToken($enrollment, $lesson));

        $token = DB::transaction(function () use ($enrollment, $lesson, $generatesToken, $reason, $note, $actor): ?SubstituteToken {
            $attendance = LessonAttendance::updateOrCreate(
                ['client_id' => $enrollment->client_id, 'lesson_id' => $lesson->getKey()],
                [
                    'enrollment_id' => $enrollment->getKey(),
                    'attended' => false,
                    'cancelled_at' => now(),
                    'excuse_reason' => $reason,
                    'excuse_note' => $note,
                    'excused_by_id' => $actor?->getKey(),
                    'token_generated' => $generatesToken,
                ],
            );

            if (! $generatesToken) {
                return null;
            }

            return SubstituteToken::create([
                'client_id' => $enrollment->client_id,
                'source_lesson_id' => $lesson->getKey(),
                'source_attendance_id' => $attendance->getKey(),
                'expires_at' => now()->addDays(Settings::substituteTokenValidityDays()),
            ]);
        });

        if ($notifyClient) {
            $this->notify($enrollment, $lesson, $token);
        }

        return $token;
    }

    /**
     * Whether excusing from this lesson right now would still mint a token —
     * used by the UI to label the button honestly before the client clicks.
     */
    public function wouldGenerateToken(CourseEnrollment $enrollment, Lesson $lesson): bool
    {
        $course = $enrollment->series?->course;

        return $this->isTimely($lesson, (int) ($course?->early_cancel_hours ?? 0))
            && $this->substitutesRemaining($enrollment) > 0;
    }

    /**
     * How many make-ups this série grants over its whole run. It belongs to the
     * série rather than the course because séries differ in length — a ten-lesson
     * run and a six-lesson one cannot share one number.
     */
    public function substitutesAllowance(CourseEnrollment $enrollment): int
    {
        return (int) ($enrollment->series?->max_substitutions ?? 0);
    }

    public function substitutesUsed(CourseEnrollment $enrollment): int
    {
        return LessonAttendance::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('token_generated', true)
            ->count();
    }

    public function substitutesRemaining(CourseEnrollment $enrollment): int
    {
        return max(0, $this->substitutesAllowance($enrollment) - $this->substitutesUsed($enrollment));
    }

    protected function guard(CourseEnrollment $enrollment, Lesson $lesson, bool $allowPast = false): void
    {
        if ($lesson->series_id !== $enrollment->series_id) {
            throw new SubstituteException('Tato lekce nepatří do vašeho kurzu.');
        }

        if (! $allowPast && $this->lessonStart($lesson)->isPast()) {
            throw new SubstituteException('Z proběhlé lekce se odhlásit nelze.');
        }

        $alreadyExcused = LessonAttendance::query()
            ->where('client_id', $enrollment->client_id)
            ->where('lesson_id', $lesson->getKey())
            ->whereNotNull('cancelled_at')
            ->exists();

        if ($alreadyExcused) {
            throw new SubstituteException('Z této lekce už jste odhlášeni.');
        }
    }

    /**
     * A minted token brings its own e-mail — it carries the expiry and the link
     * to pick a replacement. Without one the client still deserves to hear that
     * we no longer count on them.
     */
    protected function notify(CourseEnrollment $enrollment, Lesson $lesson, ?SubstituteToken $token): void
    {
        $context = [
            'jmeno' => str((string) $enrollment->client?->name)->before(' ')->toString(),
            'kurz' => (string) ($enrollment->series?->course?->name ?? ''),
            'lekce' => $this->lessonLabel($lesson),
        ];

        if ($token === null) {
            $enrollment->client?->notify(new SubstituteTokenNotification(
                EmailTemplateKey::LessonExcused,
                $context,
            ));

            return;
        }

        $enrollment->client?->notify(new SubstituteTokenNotification(
            EmailTemplateKey::SubstituteTokenGenerated,
            [
                ...$context,
                'platnost' => $token->expires_at->format('j. n. Y'),
                'odkaz' => url('/muj-ucet/nahrady'),
            ],
        ));
    }

    protected function isTimely(Lesson $lesson, int $earlyCancelHours): bool
    {
        return now()->lessThanOrEqualTo($this->lessonStart($lesson)->subHours($earlyCancelHours));
    }

    protected function lessonStart(Lesson $lesson): Carbon
    {
        return $lesson->lesson_date->copy()->setTimeFromTimeString((string) $lesson->start_time);
    }

    protected function lessonLabel(Lesson $lesson): string
    {
        return $this->lessonStart($lesson)->format('j. n. Y · H:i');
    }
}
