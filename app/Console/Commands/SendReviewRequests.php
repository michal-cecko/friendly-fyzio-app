<?php

namespace App\Console\Commands;

use App\Enums\ReviewRequestChannel;
use App\Models\CourseSeries;
use App\Models\ReviewRequest;
use App\Models\User;
use App\Models\Workshop;
use App\Notifications\ReviewRequestNotification;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Emails course and workshop participants a link to an external review
 * questionnaire a couple of days after their event ends. Runs daily; a small
 * catch-up window and a per-participant dedup check keep it idempotent, so a
 * missed run is recovered and nobody is asked twice for the same event.
 */
class SendReviewRequests extends Command
{
    protected $signature = 'reviews:send-requests';

    protected $description = 'Send review-request e-mails to participants of courses and workshops that ended recently';

    public function handle(): int
    {
        if (! Settings::get('reviews.enabled', false)) {
            $this->info('Automatické žádosti o recenzi jsou vypnuté.');

            return self::SUCCESS;
        }

        $daysAfter = (int) Settings::get('reviews.days_after', 2);
        $defaultUrl = Settings::get('reviews.questionnaire_url');

        // Events that ended between N+2 and N days ago (inclusive): the N-day target
        // plus a 2-day cushion so a skipped daily run is still caught up.
        $windowStart = now()->subDays($daysAfter + 2)->toDateString();
        $windowEnd = now()->subDays($daysAfter)->toDateString();

        $sent = 0;

        CourseSeries::query()
            ->with('course')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $windowStart)
            ->whereDate('end_date', '<=', $windowEnd)
            ->get()
            ->each(function (CourseSeries $series) use ($defaultUrl, &$sent): void {
                $url = filled($series->course?->questionnaire_url)
                    ? $series->course->questionnaire_url
                    : $defaultUrl;

                if (blank($url)) {
                    return;
                }

                $sent += $this->notifyParticipants($series, $this->clientsFrom($series->enrollments()->with('client')->get()), $url);
            });

        Workshop::query()
            ->whereDate('workshop_date', '>=', $windowStart)
            ->whereDate('workshop_date', '<=', $windowEnd)
            ->get()
            ->each(function (Workshop $workshop) use ($defaultUrl, &$sent): void {
                $url = filled($workshop->questionnaire_url)
                    ? $workshop->questionnaire_url
                    : $defaultUrl;

                if (blank($url)) {
                    return;
                }

                $sent += $this->notifyParticipants($workshop, $this->clientsFrom($workshop->registrations()->with('client')->get()), $url);
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
    private function notifyParticipants(Model $reviewable, Collection $clients, string $url): int
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

            ReviewRequest::create([
                'user_id' => $client->getKey(),
                'reviewable_type' => $reviewable->getMorphClass(),
                'reviewable_id' => $reviewable->getKey(),
                'channel' => ReviewRequestChannel::Automatic,
                'questionnaire_url' => $url,
                'sent_at' => now(),
            ]);

            $client->notify(new ReviewRequestNotification($reviewable, $url));
            $sent++;
        }

        return $sent;
    }
}
