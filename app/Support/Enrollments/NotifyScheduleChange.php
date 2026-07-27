<?php

namespace App\Support\Enrollments;

use App\Enums\EmailTemplateKey;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Emails\MessageBlock;
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
     * @return int Number of recipients (participants + instructor) e-mailed.
     */
    public function __invoke(Lesson $scheduled, array $snapshot = [], ?string $reason = null): int
    {
        $tokens = [
            'nazev' => $this->name($scheduled),
            'termin' => EnrollmentEmailContext::dateTimeLabel($scheduled->startsAt()),
            'misto' => EnrollmentEmailContext::place($scheduled->room),
            'duvod' => (string) ($reason ?? ''),
            'zprava' => MessageBlock::render($reason),
            ...$snapshot,
        ];

        $notified = 0;

        foreach ($this->clients($scheduled) as $client) {
            if ($client !== null && filled($client->email)) {
                $client->notify(new EnrollmentTemplateNotification(EmailTemplateKey::LessonScheduleChanged, [
                    'jmeno' => EnrollmentEmailContext::firstName($client),
                    ...$tokens,
                ]));

                $notified++;
            }
        }

        $instructor = $scheduled->instructor;

        if ($instructor !== null && filled($instructor->email)) {
            $instructor->notify(new EnrollmentTemplateNotification(EmailTemplateKey::TherapistLessonScheduleChanged, [
                'jmeno' => EnrollmentEmailContext::firstName($instructor),
                ...$tokens,
            ]));

            $notified++;
        }

        return $notified;
    }

    protected function name(Lesson $scheduled): string
    {
        return $scheduled->series !== null
            ? EnrollmentEmailContext::offerTokens($scheduled->series)['nazev']
            : $scheduled->displayName();
    }

    /**
     * Everyone who was going to be in the room: for a lesson of a série that is
     * the run's active enrollees (enrollment lives at the série level), and for
     * every lesson it also includes anyone who bought this single session.
     * Duplicates are dropped — a client can be both.
     *
     * @return Collection<int, User|null>
     */
    protected function clients(Lesson $scheduled): Collection
    {
        $enrolled = $scheduled->series?->activeTakers()->with('client')->get() ?? collect();
        $dropIns = $scheduled->activeTakers()->with('client')->get();

        return $enrolled
            ->concat($dropIns)
            ->map(fn ($signup) => $signup->client)
            ->filter()
            ->unique(fn (User $client): string => (string) $client->getKey())
            ->values();
    }
}
