<?php

namespace App\Support\Enrollments;

use App\Enums\EmailTemplateKey;
use App\Models\CourseLesson;
use App\Models\OneOffEvent;
use App\Models\User;
use App\Notifications\EnrollmentTemplateNotification;
use Illuminate\Support\Collection;

/**
 * Notifies the enrolled participants — and the instructor — that a session's
 * schedule (date, time or room) changed. Works uniformly for a course lesson and a
 * one-off event through the same union-type + activeTakers()
 * convention the rest of the enrollment engine uses ({@see SignUpForOffer},
 * {@see CancelSignup}); course-lesson recipients are the whole run's active
 * enrollees, since enrollment lives at the series level.
 *
 * The old term arrives as $snapshot ({{ puvodni_termin }} / {{ puvodni_misto }}),
 * captured before the edit was saved by {@see OfferScheduleSnapshot}.
 */
class NotifyScheduleChange
{
    /**
     * @param  array<string, string>  $snapshot
     */
    public function __invoke(CourseLesson|OneOffEvent $scheduled, array $snapshot = [], ?string $reason = null): void
    {
        $tokens = [
            'nazev' => $this->name($scheduled),
            'termin' => EnrollmentEmailContext::dateTimeLabel($scheduled->startsAt()),
            'misto' => EnrollmentEmailContext::place($scheduled->room),
            'duvod' => (string) ($reason ?? ''),
            ...$snapshot,
        ];

        foreach ($this->clients($scheduled) as $client) {
            if ($client !== null && filled($client->email)) {
                $client->notify(new EnrollmentTemplateNotification(EmailTemplateKey::LessonScheduleChanged, [
                    'jmeno' => EnrollmentEmailContext::firstName($client),
                    ...$tokens,
                ]));
            }
        }

        $instructor = $scheduled->instructor;

        if ($instructor !== null && filled($instructor->email)) {
            $instructor->notify(new EnrollmentTemplateNotification(EmailTemplateKey::TherapistLessonScheduleChanged, [
                'jmeno' => EnrollmentEmailContext::firstName($instructor),
                ...$tokens,
            ]));
        }
    }

    protected function name(CourseLesson|OneOffEvent $scheduled): string
    {
        return match (true) {
            $scheduled instanceof CourseLesson => $scheduled->series !== null
                ? EnrollmentEmailContext::offerTokens($scheduled->series)['nazev']
                : '',
            $scheduled instanceof OneOffEvent => $scheduled->name,
        };
    }

    /**
     * The account holders to notify: active enrollees of the changed session.
     *
     * @return Collection<int, User|null>
     */
    protected function clients(CourseLesson|OneOffEvent $scheduled): Collection
    {
        $signups = match (true) {
            $scheduled instanceof CourseLesson => $scheduled->series?->activeTakers()->with('client')->get() ?? collect(),
            default => $scheduled->activeTakers()->with('client')->get(),
        };

        return $signups->map(fn ($signup) => $signup->client);
    }
}
