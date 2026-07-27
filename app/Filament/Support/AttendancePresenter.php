<?php

namespace App\Filament\Support;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\LessonBookingResource;
use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\SubstituteToken;
use Filament\Support\Icons\Heroicon;

/**
 * How a seat on a lesson's presence list reads: where the client got it, whether
 * we are counting on them, and what their missed lesson was swapped for. Shared
 * by the lesson's Docházka section, the standalone docházka table and the
 * attendance detail page, so all three describe the same row the same way.
 */
class AttendancePresenter
{
    /**
     * Presence is a decision, not a record of ticking: everybody on the list is
     * expected until somebody says otherwise. Only an excuse makes a row absent.
     */
    public static function isPresent(LessonAttendance $record): bool
    {
        return ! $record->isExcused();
    }

    public static function presenceLabel(LessonAttendance $record): string
    {
        return self::isPresent($record) ? 'Přítomen' : 'Nepřítomen';
    }

    public static function presenceIcon(LessonAttendance $record): Heroicon
    {
        return self::isPresent($record) ? Heroicon::CheckCircle : Heroicon::OutlinedNoSymbol;
    }

    public static function presenceColor(LessonAttendance $record): string
    {
        return self::isPresent($record) ? 'success' : 'warning';
    }

    /**
     * How this person came by their seat — which is also the difference between
     * somebody the course rules apply to and somebody who simply paid for one
     * evening. A sign-up that has since been cancelled says so first: the row is
     * kept for history and no longer holds a place.
     */
    public static function originLabel(LessonAttendance $record): string
    {
        return match (true) {
            self::isCancelled($record) => 'Zrušená přihláška',
            $record->isDropIn() => 'Jednorázový vstup',
            $record->isSubstituteGuest() => 'Náhrada',
            default => 'Kurz',
        };
    }

    public static function originColor(LessonAttendance $record): string
    {
        return match (true) {
            self::isCancelled($record) => 'danger',
            $record->isDropIn() => 'info',
            $record->isSubstituteGuest() => 'warning',
            default => 'gray',
        };
    }

    /**
     * The sign-up behind the seat: the drop-in purchase, or the enrollment in the
     * série (which for a substitute is a different run than this lesson's).
     */
    public static function seatUrl(LessonAttendance $record): ?string
    {
        if ($record->booking !== null) {
            return LessonBookingResource::getUrl('view', ['record' => $record->booking]);
        }

        return $record->enrollment !== null
            ? CourseEnrollmentResource::getUrl('view', ['record' => $record->enrollment])
            : null;
    }

    /**
     * Whether the sign-up behind this seat has been cancelled — by staff, or by
     * the unpaid-hold sweep. {@see Lesson::presentSeats()} stops
     * counting these; the list stops showing them unless asked.
     */
    public static function isCancelled(LessonAttendance $record): bool
    {
        if ($record->booking !== null) {
            return ! in_array($record->booking->status, BookingStatus::occupying(), true);
        }

        return $record->enrollment?->status === CourseEnrollmentStatus::Cancelled;
    }

    public static function substituteLabel(LessonAttendance $record): ?string
    {
        if (($source = $record->replacementFor) !== null) {
            return 'Náhrada za · '.self::lessonLabel($source->lesson);
        }

        if (($replacement = $record->replacement) !== null) {
            return 'Nahrazeno · '.self::lessonLabel($replacement->lesson);
        }

        if (! $record->isExcused()) {
            return null;
        }

        $token = self::token($record);

        if ($token === null) {
            return 'Bez náhrady';
        }

        return $token->expires_at?->isPast()
            ? 'Poukaz propadl'
            : 'Poukaz nevybrán (platí do '.$token->expires_at?->format('j. n. Y').')';
    }

    public static function substituteIcon(LessonAttendance $record): ?Heroicon
    {
        if ($record->replacementFor !== null) {
            return Heroicon::OutlinedArrowUturnLeft;
        }

        if ($record->replacement !== null) {
            return Heroicon::OutlinedArrowUturnRight;
        }

        return $record->isExcused() && self::token($record) !== null
            ? Heroicon::OutlinedTicket
            : null;
    }

    public static function substituteColor(LessonAttendance $record): string
    {
        if ($record->replacementFor !== null || $record->replacement !== null) {
            return 'info';
        }

        $token = self::token($record);

        return $token !== null && ! $token->expires_at?->isPast() ? 'warning' : 'gray';
    }

    public static function substituteUrl(LessonAttendance $record): ?string
    {
        if (($source = $record->replacementFor?->lesson) !== null) {
            return LessonResource::getUrl('view', ['record' => $source]);
        }

        if (($replacement = $record->replacement?->lesson) !== null) {
            return LessonResource::getUrl('view', ['record' => $replacement]);
        }

        $token = self::token($record);
        $client = $record->client;

        return $token !== null && ! $token->expires_at?->isPast() && $client !== null
            ? ClientResource::getUrl('view', ['record' => $client])
            : null;
    }

    /**
     * A lesson named the way a person would say it out loud: when it is, and
     * which run it belongs to.
     */
    public static function lessonLabel(?Lesson $lesson): string
    {
        if ($lesson === null) {
            return 'jiná lekce';
        }

        return $lesson->startsAt()->format('j. n. Y · H:i')
            .($lesson->series?->name !== null ? ' · '.$lesson->series->name : '');
    }

    /**
     * The poukaz this excuse minted, if it minted one at all.
     */
    private static function token(LessonAttendance $record): ?SubstituteToken
    {
        return $record->token_generated ? $record->substituteToken : null;
    }
}
