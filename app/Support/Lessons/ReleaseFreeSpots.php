<?php

namespace App\Support\Lessons;

use App\Enums\EmailTemplateKey;
use App\Enums\OfferVisibility;
use App\Models\EventCategory;
use App\Models\Lesson;
use App\Models\WaitlistEntry;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\EnrollmentEmailContext;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

/**
 * The last two stages of what happens to a free place on a course lesson, per
 * FSS §6.4: "Systém oslovuje ľudí na čakacej listine … Ak nikto nereaguje,
 * miesto sa uvoľní pre verejnosť."
 *
 * Stage 1 already works — holders of a náhrada poukaz see free places in the
 * client zone. This adds:
 *
 *   2. the série's čekací listina gets first refusal, for a grace window;
 *   3. whatever is left goes on public sale as a jednorázová lekce.
 *
 * Because a lesson and a jednorázová lekce are one record, "going public" is
 * just publishing the lesson that already exists — no second row, and the same
 * {@see Lesson::takenSpots()} governs both the roster and the drop-in seats.
 *
 * A course with no `drop_in_price` never takes part: pricing a lesson is the
 * deliberate act that opts a course into selling seats one at a time.
 */
class ReleaseFreeSpots
{
    /**
     * @return array{invited: int, released: int, withdrawn: int}
     */
    public function __invoke(): array
    {
        $result = ['invited' => 0, 'released' => 0, 'withdrawn' => 0];
        $offeredSeries = [];

        foreach ($this->candidates() as $lesson) {
            if ($this->withdraw($lesson)) {
                $result['withdrawn']++;

                continue;
            }

            if ($lesson->isReleased() || ! $this->sellable($lesson)) {
                continue;
            }

            // The place belongs to the waitlist until their window runs out.
            if ($lesson->waitlistInviteActive()) {
                $offeredSeries[$lesson->series_id] = true;

                continue;
            }

            // Never offered to them yet — do that first, and only fall through
            // to the public when there is nobody waiting.
            if ($lesson->waitlist_invited_until === null) {
                // One lesson per série at a time. People stay on the list and
                // will be offered the later dates too, but a course with twenty
                // free evenings must not land twenty e-mails in one inbox at
                // once — candidates() is date-ordered, so the soonest goes first.
                if (isset($offeredSeries[$lesson->series_id])) {
                    continue;
                }

                $invited = $this->inviteWaitlist($lesson);

                if ($invited > 0) {
                    $offeredSeries[$lesson->series_id] = true;
                    $result['invited'] += $invited;

                    continue;
                }
            }

            $result['released'] += $this->release($lesson) ? 1 : 0;
        }

        return $result;
    }

    /**
     * Upcoming lessons of a série whose course prices single seats, plus the
     * already-released ones so a place that filled up again can be withdrawn.
     *
     * @return LazyCollection<int, Lesson>
     */
    protected function candidates()
    {
        return Lesson::query()
            ->whereNotNull('series_id')
            ->whereDate('lesson_date', '>=', today())
            ->whereHas('series.course', fn (Builder $query) => $query->whereNotNull('drop_in_price'))
            ->with(['series.course', 'category'])
            ->withOccupancyCounts()
            ->orderBy('lesson_date')
            ->cursor();
    }

    /**
     * A place is worth offering while there is one free, the course prices it,
     * and the sales window has not closed.
     */
    protected function sellable(Lesson $lesson): bool
    {
        return $lesson->spotsLeft() > 0
            && $lesson->price !== null
            && $this->cutoff($lesson)->isFuture();
    }

    /**
     * A released lesson that filled up again — or passed its cutoff — comes back
     * off sale. Once somebody has bought a seat it stays public: taking the page
     * away under a paying customer would be worse than leaving it up.
     */
    protected function withdraw(Lesson $lesson): bool
    {
        if (! $lesson->isReleased()) {
            return false;
        }

        if ($lesson->dropInCount() > 0) {
            return false;
        }

        if ($lesson->spotsLeft() > 0 && $this->cutoff($lesson)->isFuture()) {
            return false;
        }

        $lesson->forceFill([
            'released_at' => null,
            'published_at' => null,
            'waitlist_invited_until' => null,
        ])->save();

        return true;
    }

    /**
     * The waitlist keeps priority for a while — but never past the point where
     * the public would have no chance at all. The window is the configured
     * number of hours, shortened to half the time left before the cutoff when
     * the lesson is closer than that, and skipped entirely when it is imminent.
     */
    protected function waitlistWindow(Lesson $lesson): ?Carbon
    {
        $cutoff = $this->cutoff($lesson);
        $minutesLeft = now()->diffInMinutes($cutoff, false);

        if ($minutesLeft <= 0) {
            return null;
        }

        $window = min(Settings::waitlistInviteHours() * 60, (int) floor($minutesLeft / 2));

        return $window < 1 ? null : now()->addMinutes($window);
    }

    protected function cutoff(Lesson $lesson): Carbon
    {
        return $lesson->startsAt()->subHours(Settings::dropInCutoffHours());
    }

    /**
     * Gives the série's čekací listina first refusal. The lesson gets everything
     * it needs to be reachable — category, name, slug, presale token — but stays
     * unpublished, so only the hidden link in the e-mail gets through
     * ({@see Lesson::offerStateForPresale()} ignores the publish state).
     *
     * Unlike {@see InviteWaitlistToSpot} the entries are NOT consumed. Those
     * people are waiting for a place in the whole course; being offered one
     * trial lesson must not cost them their position in that queue.
     */
    protected function inviteWaitlist(Lesson $lesson): int
    {
        $until = $this->waitlistWindow($lesson);

        if ($until === null) {
            return 0;
        }

        $entries = $lesson->series?->waitlistEntries()->pending()->with('client')->get() ?? collect();

        if ($entries->isEmpty()) {
            return 0;
        }

        if (! $this->prepare($lesson)) {
            return 0;
        }

        $lesson->forceFill(['waitlist_invited_until' => $until])->save();

        $invited = 0;

        foreach ($entries as $entry) {
            if ($this->notifyWaiter($entry, $lesson, $until)) {
                $invited++;
            }
        }

        return $invited;
    }

    protected function notifyWaiter(WaitlistEntry $entry, Lesson $lesson, Carbon $until): bool
    {
        $email = $entry->displayEmail();

        if ($email === null) {
            return false;
        }

        $notification = new EnrollmentTemplateNotification(EmailTemplateKey::WaitlistSpotOffered, [
            'jmeno' => $entry->client !== null
                ? EnrollmentEmailContext::firstName($entry->client)
                : (string) str($entry->displayName())->before(' '),
            'nazev' => $lesson->displayName(),
            'termin' => EnrollmentEmailContext::dateTimeLabel($lesson->startsAt()),
            'lhuta_hodin' => (string) max(1, (int) round(now()->diffInMinutes($until, false) / 60)),
            'odkaz' => $lesson->presaleUrl(),
        ]);

        if ($entry->client !== null) {
            $entry->client->notify($notification);
        } else {
            Notification::route('mail', $email)->notify($notification);
        }

        return true;
    }

    /**
     * Fills in what a public address needs, without publishing anything.
     */
    protected function prepare(Lesson $lesson): bool
    {
        $category = $this->category();

        if ($category === null) {
            return false;
        }

        $lesson->forceFill([
            'event_category_id' => $lesson->event_category_id ?? $category->getKey(),
            'name' => $lesson->name ?? $lesson->displayName(),
            'slug' => $lesson->slug ?? $this->slug($lesson),
        ])->save();

        $lesson->ensurePresaleToken();

        return true;
    }

    /**
     * Puts the lesson on public sale, filling in whatever the public surface
     * needs. Description and image need nothing — {@see Lesson::displayDescription()}
     * already falls back to the course, live rather than copied.
     */
    protected function release(Lesson $lesson): bool
    {
        $category = $this->category();

        if ($category === null) {
            return false;
        }

        DB::transaction(function () use ($lesson, $category): void {
            $lesson->forceFill([
                'event_category_id' => $lesson->event_category_id ?? $category->getKey(),
                'name' => $lesson->name ?? $lesson->displayName(),
                'slug' => $lesson->slug ?? $this->slug($lesson),
                'visibility' => OfferVisibility::Public,
                'published_at' => now(),
                'released_at' => now(),
                'waitlist_invited_until' => null,
            ])->save();
        });

        return true;
    }

    protected function category(): ?EventCategory
    {
        return EventCategory::query()->where('slug', Settings::dropInCategorySlug())->first();
    }

    /**
     * `{course-slug}-{Y-m-d}`, the convention the 2026-07-18 conversion used, so
     * addresses stay consistent with the events that came before.
     */
    protected function slug(Lesson $lesson): string
    {
        $base = Str::slug(($lesson->offerCourse()?->slug ?? 'lekce').'-'.$lesson->lesson_date->format('Y-m-d'));
        $slug = $base;
        $suffix = 2;

        while (Lesson::query()->where('slug', $slug)->whereKeyNot($lesson->getKey())->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
