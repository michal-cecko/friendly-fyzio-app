<?php

namespace App\Support\Enrollments;

use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\OneTimeLesson;
use App\Models\OneTimeLessonBooking;
use App\Models\Room;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Builds the {{ token }} context for the course/lesson/workshop e-mails, the
 * counterpart of ReservationEmailContext for enrollables. Payment tokens
 * (castka/iban/vs/qr/splatnost) are merged in by the caller from
 * PaymentEmailTokens so they stay consistent with every other payment e-mail.
 */
class EnrollmentEmailContext
{
    /**
     * @return array<string, string>
     */
    public static function forEnrollment(CourseEnrollment $enrollment, array $extra = []): array
    {
        $series = $enrollment->series;

        return [
            'jmeno' => self::firstName($enrollment->client),
            'kurz' => (string) ($series?->course?->name ?? ''),
            'beh' => (string) ($series?->name ?? ''),
            'obdobi' => $series !== null ? self::seriesPeriod($series) : '',
            'rozvrh' => $series !== null ? self::nextLessonLabel($series) : '',
            'rezervace_hodin' => (string) Settings::enrollmentHoldHours(),
            ...$extra,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function forRegistration(WorkshopRegistration $registration, array $extra = []): array
    {
        $workshop = $registration->workshop;

        return [
            'jmeno' => self::firstName($registration->client),
            'workshop' => (string) ($workshop?->name ?? ''),
            'termin' => $workshop !== null ? self::dateTimeLabel($workshop->startsAt()) : '',
            'misto' => self::place($workshop?->room),
            'rezervace_hodin' => (string) Settings::enrollmentHoldHours(),
            ...$extra,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function forBooking(OneTimeLessonBooking $booking, array $extra = []): array
    {
        $lesson = $booking->lesson;

        return [
            'jmeno' => self::firstName($booking->client),
            'lekce' => (string) ($lesson?->course?->name ?? ''),
            'termin' => $lesson !== null ? self::dateTimeLabel($lesson->startsAt()) : '',
            'misto' => self::place($lesson?->room),
            'rezervace_hodin' => (string) Settings::enrollmentHoldHours(),
            ...$extra,
        ];
    }

    /**
     * The generic {{ nazev }} / {{ termin }} pair describing an offer, used by
     * the shared auto-cancel and waitlist e-mails.
     *
     * @return array<string, string>
     */
    public static function offerTokens(CourseSeries|OneTimeLesson|Workshop $offer): array
    {
        return match (true) {
            $offer instanceof CourseSeries => [
                'nazev' => trim(($offer->course?->name ?? '').' ('.$offer->name.')'),
                'termin' => self::seriesPeriod($offer),
            ],
            $offer instanceof OneTimeLesson => [
                'nazev' => (string) ($offer->course?->name ?? ''),
                'termin' => self::dateTimeLabel($offer->startsAt()),
            ],
            $offer instanceof Workshop => [
                'nazev' => $offer->name,
                'termin' => self::dateTimeLabel($offer->startsAt()),
            ],
        };
    }

    public static function firstName(?User $client): string
    {
        $name = (string) ($client?->name ?? '');

        return Str::of($name)->before(' ')->toString() ?: $name;
    }

    public static function seriesPeriod(CourseSeries $series): string
    {
        return implode(' – ', array_filter([
            $series->start_date?->format('j. n. Y'),
            $series->end_date?->format('j. n. Y'),
        ]));
    }

    public static function dateTimeLabel(Carbon $moment): string
    {
        return $moment->format('j. n. Y').', '.$moment->format('H:i');
    }

    /**
     * "Nejbližší lekce" of a series: the first lesson from today on (falls back
     * to the series start date when no lessons are planned yet).
     */
    public static function nextLessonLabel(CourseSeries $series): string
    {
        $lesson = $series->lessons()
            ->whereDate('lesson_date', '>=', today())
            ->orderBy('lesson_date')
            ->orderBy('start_time')
            ->first();

        if ($lesson === null) {
            return (string) $series->start_date?->format('j. n. Y');
        }

        return self::dateTimeLabel(Carbon::parse($lesson->lesson_date->format('Y-m-d').' '.$lesson->start_time));
    }

    public static function place(?Room $room): string
    {
        $address = $room?->building?->address;

        return filled($address) ? (string) $address : (string) (Settings::get('web.address') ?? '');
    }
}
