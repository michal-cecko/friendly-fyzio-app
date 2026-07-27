<?php

namespace App\Support\Substitutes;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\SubstituteToken;
use App\Notifications\SubstituteTokenNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Redeeming a substitute token against a chosen lesson. Serialised per target
 * lesson with a cache lock and re-checked inside the transaction, so two
 * clients racing for the last substitute place can't both win.
 *
 * The attendance is recorded against the client's own (source) enrollment with
 * the target lesson id — lesson_attendances.enrollment_id is NOT NULL and the
 * client has no enrollment in the target series, so rosters must read the
 * lesson's attendances rather than assume enrollment.series_id === lesson.series_id.
 */
class RedeemToken
{
    public function __construct(protected SubstituteOptions $options) {}

    public function __invoke(SubstituteToken $token, Lesson $target): LessonAttendance
    {
        $lock = Cache::lock("lesson:{$target->getKey()}", 10);

        /** @var LessonAttendance */
        return $lock->block(5, function () use ($token, $target): LessonAttendance {
            $attendance = DB::transaction(fn (): LessonAttendance => $this->persist($token, $target));

            $token->client?->notify(new SubstituteTokenNotification(
                EmailTemplateKey::SubstituteTokenRedeemed,
                [
                    'jmeno' => str((string) $token->client?->name)->before(' ')->toString(),
                    'kurz' => (string) ($target->series?->course?->name ?? ''),
                    'lekce' => $target->lesson_date->copy()->setTimeFromTimeString((string) $target->start_time)->format('j. n. Y · H:i'),
                    'misto' => (string) ($target->room?->name ?? ''),
                ],
            ));

            return $attendance;
        });
    }

    protected function persist(SubstituteToken $token, Lesson $target): LessonAttendance
    {
        if ($token->used_at !== null) {
            throw new SubstituteException('Tento náhradní vstup už byl uplatněn.');
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            throw new SubstituteException('Platnost náhradního vstupu vypršela.');
        }

        if (! $this->options->forToken($token)->contains(fn (Lesson $lesson): bool => $lesson->is($target))) {
            throw new SubstituteException('Tuto lekci si jako náhradu vybrat nelze.');
        }

        // Re-check capacity inside the lock — someone may have taken the last spot.
        if ($this->options->freeSpots($target) < 1) {
            throw new SubstituteException('Tato lekce je už obsazená. Vyberte prosím jiný termín.');
        }

        $sourceEnrollment = $this->sourceEnrollment($token);

        $token->update([
            'used_at' => now(),
            'used_for_lesson_id' => $target->getKey(),
        ]);

        $attendance = LessonAttendance::create([
            'client_id' => $token->client_id,
            'enrollment_id' => $sourceEnrollment->getKey(),
            'lesson_id' => $target->getKey(),
            'attended' => true,
        ]);

        $this->linkToSource($token, $attendance);

        return $attendance;
    }

    /**
     * Point the excused row at the row that makes it up, so the presence list of
     * either lesson can name the other.
     */
    protected function linkToSource(SubstituteToken $token, LessonAttendance $replacement): void
    {
        $source = $token->sourceAttendance ?? LessonAttendance::query()
            ->where('lesson_id', $token->source_lesson_id)
            ->where('client_id', $replacement->client_id)
            ->first();

        $source?->update(['replacement_attendance_id' => $replacement->getKey()]);
    }

    protected function sourceEnrollment(SubstituteToken $token): CourseEnrollment
    {
        $enrollment = CourseEnrollment::query()
            ->where('client_id', $token->client_id)
            ->where('series_id', $token->sourceLesson?->series_id)
            ->where('status', CourseEnrollmentStatus::Active)
            ->first();

        if ($enrollment === null) {
            throw new SubstituteException('K náhradnímu vstupu se nepodařilo najít vaši přihlášku do kurzu.');
        }

        return $enrollment;
    }
}
