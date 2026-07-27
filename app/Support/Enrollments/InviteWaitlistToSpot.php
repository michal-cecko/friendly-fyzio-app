<?php

namespace App\Support\Enrollments;

use App\Enums\EmailTemplateKey;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\WaitlistEntry;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Settings;
use Illuminate\Support\Facades\Notification;

/**
 * The invite round of docs §6.4 ("Automatický náhradník: prvý kto potvrdí,
 * miesto dostáva. Ak nikto nereaguje, miesto sa uvoľní pre verejnosť"): every
 * waiter is e-mailed at once and races for the freed spot, exactly like the
 * therapist day-waitlist does for reservations.
 *
 * Unlike {@see OfferSpotToEntry} nothing is booked here — no account, no
 * sign-up, no payment request. The spot is only fenced off from the public for
 * the invite window by stamping `waitlist_invited_until`, which makes
 * `offerState()` report Full while the offer's hidden link (`presaleUrl()`,
 * which reads `offerStateForPresale()`) still lets an invited waiter through.
 * Once the stamp passes, the offer opens to everyone with no further work —
 * the deadline is evaluated lazily, so no scheduled command is involved.
 */
class InviteWaitlistToSpot
{
    /**
     * @return int number of waiters e-mailed (0 when the round was a no-op)
     */
    public function handle(CourseSeries|Lesson $offer): int
    {
        $offer->refresh();

        if (! $this->offerOpenForInvites($offer) || $offer->spotsLeft() <= 0) {
            return 0;
        }

        // A round already running covers any further spot freed inside its
        // window — the same cohort simply races for one more place.
        if ($offer->waitlistInviteActive()) {
            return 0;
        }

        $entries = $offer->waitlistEntries()->pending()->with('client')->get();

        if ($entries->isEmpty()) {
            return 0;
        }

        $offer->forceFill([
            'waitlist_invited_until' => now()->addHours(Settings::waitlistInviteHours()),
        ])->save();

        $invited = 0;

        foreach ($entries as $entry) {
            if ($this->notify($entry, $offer)) {
                $invited++;
            }

            // Consumed either way: a waiter without a usable e-mail must not
            // keep the queue from draining.
            $entry->forceFill(['notified_at' => now()])->save();
        }

        return $invited;
    }

    protected function notify(WaitlistEntry $entry, CourseSeries|Lesson $offer): bool
    {
        $email = $entry->displayEmail();

        if ($email === null) {
            return false;
        }

        $notification = new EnrollmentTemplateNotification(EmailTemplateKey::WaitlistSpotOffered, [
            'jmeno' => $entry->client !== null
                ? EnrollmentEmailContext::firstName($entry->client)
                : (string) str($entry->displayName())->before(' '),
            ...EnrollmentEmailContext::offerTokens($offer),
            'lhuta_hodin' => (string) Settings::waitlistInviteHours(),
            'odkaz' => $offer->presaleUrl(),
        ]);

        if ($entry->client !== null) {
            $entry->client->notify($notification);
        } else {
            Notification::route('mail', $email)->notify($notification);
        }

        return true;
    }

    protected function offerOpenForInvites(CourseSeries|Lesson $offer): bool
    {
        return match (true) {
            $offer instanceof CourseSeries => ! $offer->hasEnded(),
            $offer instanceof Lesson => ! $offer->isPast(),
        };
    }
}
