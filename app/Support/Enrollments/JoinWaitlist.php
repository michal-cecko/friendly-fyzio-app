<?php

namespace App\Support\Enrollments;

use App\Enums\EmailTemplateKey;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\OneTimeLesson;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\Workshop;
use App\Notifications\EnrollmentTemplateNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Adds someone to a waitlist. Two flavours share the storage:
 *
 * - a FULL offer (course series / one-time lesson / workshop) — the classic
 *   queue, confirmed by the "waitlist_joined" e-mail with the position;
 * - a Course — the "chci vědět první" interest list of a course that has no
 *   open registration yet; those get e-mailed when a series opens.
 *
 * No account is created here (joining is non-binding); an existing account is
 * linked by e-mail so the queue shows the known client.
 */
class JoinWaitlist
{
    public static function handle(
        Course|CourseSeries|OneTimeLesson|Workshop $waitlistable,
        ?string $name,
        string $email,
        ?string $phone = null,
    ): WaitlistEntry {
        $client = User::query()->where('email', $email)->first();

        $existing = $waitlistable->waitlistEntries()
            ->whereNull('notified_at')
            ->where(fn ($query) => $query
                ->where('email', $email)
                ->when($client !== null, fn ($query) => $query->orWhere('client_id', $client->id)))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $entry = $waitlistable->waitlistEntries()->create([
            'client_id' => $client?->id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ]);

        // Interest subscriptions ("notify me when registration opens") stay
        // silent until the course actually opens; real queues get a receipt.
        if (! $waitlistable instanceof Course) {
            $position = $waitlistable->waitlistEntries()->whereNull('notified_at')->count();

            Notification::route('mail', $email)->notify(new EnrollmentTemplateNotification(
                EmailTemplateKey::WaitlistJoined,
                [
                    'jmeno' => $client !== null
                        ? EnrollmentEmailContext::firstName($client)
                        : (string) str((string) $name)->before(' '),
                    ...EnrollmentEmailContext::offerTokens($waitlistable),
                    'poradi' => (string) $position,
                ],
            ));
        }

        return $entry;
    }
}
