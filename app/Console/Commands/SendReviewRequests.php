<?php

namespace App\Console\Commands;

use App\Enums\ReviewRequestChannel;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\ReviewRequest;
use App\Models\User;
use App\Notifications\ReviewRequestNotification;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Emails course and one-off event participants a magic link to the on-site
 * review form a couple of days after their event ends. Runs daily; a small
 * catch-up window and a per-participant dedup check keep it idempotent, so a
 * missed run is recovered and nobody is asked twice for the same event.
 */
class SendReviewRequests extends Command
{
    protected $signature = 'reviews:send-requests';

    protected $description = 'Send review-request e-mails to participants of courses and one-off events that ended recently';

    public function handle(): int
    {
        if (! Settings::get('reviews.enabled', false)) {
            $this->info('Automatické žádosti o recenzi jsou vypnuté.');

            return self::SUCCESS;
        }

        $daysAfter = (int) Settings::get('reviews.days_after', 2);

        // Events that ended between N+2 and N days ago (inclusive): the N-day target
        // plus a 2-day cushion so a skipped daily run is still caught up.
        $windowStart = now()->subDays($daysAfter + 2)->toDateString();
        $windowEnd = now()->subDays($daysAfter)->toDateString();

        $sent = 0;

        CourseSeries::query()
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $windowStart)
            ->whereDate('end_date', '<=', $windowEnd)
            ->get()
            ->each(function (CourseSeries $series) use (&$sent): void {
                $sent += $this->notifyParticipants($series, $this->clientsFrom($series->enrollments()->with('client')->get()));
            });

        Lesson::query()
            ->whereDate('lesson_date', '>=', $windowStart)
            ->whereDate('lesson_date', '<=', $windowEnd)
            ->get()
            ->each(function (Lesson $event) use (&$sent): void {
                $sent += $this->notifyParticipants($event, $this->clientsFrom($event->bookings()->with('client')->get()));
            });

        $this->info("Odesláno {$sent} žádostí o recenzi.");

        return self::SUCCESS;
    }

    /**
     * Reduce a collection of enrollments/registrations to their unique clients.
     *
     * @param  Collection<int, Model>  $rows
     * @return Collection<int, User>
     */
    private function clientsFrom(Collection $rows): Collection
    {
        return $rows->pluck('client')->filter()->unique('id')->values();
    }

    /**
     * @param  Collection<int, User>  $clients
     */
    private function notifyParticipants(Model $reviewable, Collection $clients): int
    {
        $sent = 0;

        foreach ($clients as $client) {
            if (blank($client->email)) {
                continue;
            }

            $alreadySent = ReviewRequest::query()
                ->where('reviewable_type', $reviewable->getMorphClass())
                ->where('reviewable_id', $reviewable->getKey())
                ->where('user_id', $client->getKey())
                ->where('channel', ReviewRequestChannel::Automatic)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $request = ReviewRequest::create([
                'user_id' => $client->getKey(),
                'reviewable_type' => $reviewable->getMorphClass(),
                'reviewable_id' => $reviewable->getKey(),
                'channel' => ReviewRequestChannel::Automatic,
                'sent_at' => now(),
            ]);

            $client->notify(new ReviewRequestNotification($request));
            $sent++;
        }

        return $sent;
    }
}
