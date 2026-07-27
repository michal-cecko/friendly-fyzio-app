<?php

namespace App\Support\Substitutes;

use App\Models\LessonAttendance;
use App\Models\SubstituteToken;
use Illuminate\Support\Facades\DB;

/**
 * Undoes an excuse ({@see ExcuseFromLesson}): the client is present on the
 * lesson again and their spot is taken back. A substitute token minted by that
 * excuse is withdrawn with it — it was compensation for a lesson they are now
 * attending after all.
 *
 * Three situations cannot be undone, because somebody else has since acted on
 * the freed spot: a redeemed token, a make-up that already took place elsewhere,
 * and an upcoming lesson that filled up in the meantime. A lesson that has
 * already happened is exempt from the last one — its occupancy is history, and
 * staff correcting the register must not be blocked by it.
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

        if ($attendance->replacement_attendance_id !== null) {
            throw new SubstituteException('Tato lekce už byla nahrazena jinde, vrátit klienta zpět proto nelze.');
        }

        if (! $lesson->startsAt()->isPast() && $lesson->isFull()) {
            throw new SubstituteException($lesson->dropInCount() > 0
                ? 'Uvolněné místo si mezitím někdo koupil jako jednorázový vstup, vrátit klienta zpět proto nelze.'
                : 'Lekce je mezitím plná, uvolněné místo už obsadil někdo jiný.');
        }

        DB::transaction(function () use ($attendance, $token): void {
            $attendance->update([
                'attended' => true,
                'cancelled_at' => null,
                'excuse_reason' => null,
                'excuse_note' => null,
                'excused_by_id' => null,
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

        return $attendance->substituteToken;
    }
}
