<?php

namespace App\Support\Substitutes;

use App\Models\LessonAttendance;
use App\Models\SubstituteToken;
use Illuminate\Support\Facades\DB;

/**
 * Undoes an excuse ({@see ExcuseFromLesson}): the client is expected on the
 * lesson again and their spot is taken back. A substitute token minted by that
 * excuse is withdrawn with it — it was compensation for a lesson they are now
 * attending after all.
 *
 * Two situations cannot be undone, because somebody else has since acted on the
 * freed spot: a redeemed token (the make-up already happened elsewhere) and a
 * lesson that filled up in the meantime.
 */
class RestoreLessonAttendance
{
    public function __invoke(LessonAttendance $attendance): void
    {
        $lesson = $attendance->lesson;
        $enrollment = $attendance->enrollment;

        if ($lesson === null || $enrollment === null) {
            throw new SubstituteException('Lekci ani přihlášku k této docházce se nepodařilo najít.');
        }

        if ($attendance->cancelled_at === null) {
            throw new SubstituteException('Klient z této lekce odhlášený není.');
        }

        $token = $this->tokenFromThisExcuse($attendance);

        if ($token?->used_at !== null) {
            throw new SubstituteException('Klient už si za tuto lekci vybral náhradu, vrátit ho zpět proto nelze.');
        }

        if ($lesson->isFull()) {
            throw new SubstituteException('Lekce je mezitím plná, uvolněné místo už obsadil někdo jiný.');
        }

        DB::transaction(function () use ($attendance, $token): void {
            $attendance->update([
                'cancelled_at' => null,
                'token_generated' => false,
            ]);

            $token?->delete();
        });
    }

    /**
     * Whether undoing the excuse would also withdraw a substitute token — used by
     * the UI to say up front what the client loses.
     */
    public function wouldWithdrawToken(LessonAttendance $attendance): bool
    {
        return $this->tokenFromThisExcuse($attendance) !== null;
    }

    private function tokenFromThisExcuse(LessonAttendance $attendance): ?SubstituteToken
    {
        if (! $attendance->token_generated) {
            return null;
        }

        return SubstituteToken::query()
            ->where('source_lesson_id', $attendance->lesson_id)
            ->where('client_id', $attendance->enrollment?->client_id)
            ->latest('created_at')
            ->first();
    }
}
