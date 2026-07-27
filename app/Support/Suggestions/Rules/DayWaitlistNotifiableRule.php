<?php

namespace App\Support\Suggestions\Rules;

use App\Enums\SuggestionGroup;
use App\Filament\Clusters\Provoz\Resources\ReservationDayWaitlist\ReservationDayWaitlistResource;
use App\Models\ReservationDayWaitlistEntry;
use App\Support\Reservations\NotifyReservationDayWaitlist;
use App\Support\Reservations\ReservationSlots;
use App\Support\Settings;
use App\Support\StaffScope;
use App\Support\Suggestions\Suggestion;
use App\Support\Suggestions\SuggestionRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Somebody is on the pořadník for a day that has since opened up.
 *
 * Nothing notifies them on its own unless a cancellation happens to fire the
 * observer — a slot that frees up because the working hours were extended, or
 * because a therapist was added to the day, goes unannounced. This is the card
 * that says so, and "Upozornit" sends exactly what the day-waitlist action
 * sends.
 *
 * The opening check costs a handful of queries per therapist-day, so the pairs
 * are capped and the whole answer is cached briefly: two minutes of staleness
 * on a hint is harmless, a slow sidebar badge is not.
 */
class DayWaitlistNotifiableRule implements SuggestionRule
{
    /** Therapist-days examined per pass, newest need first. */
    private const MAX_PAIRS = 5;

    private const CACHE_KEY = 'suggestions:day-waitlist-notifiable';

    public function __construct(protected ReservationSlots $slots, protected NotifyReservationDayWaitlist $notifier) {}

    public function type(): string
    {
        return 'day_waitlist_notifiable';
    }

    /**
     * The pořadník itself can be switched off, and the list it links to is
     * admin-only, so a therapist never sees these.
     */
    public function isEnabled(): bool
    {
        return Settings::dayWaitlistEnabled() && ! StaffScope::current()->isScoped();
    }

    public function count(int $cap): int
    {
        return count($this->items($cap));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $cap): array
    {
        if (! $this->isEnabled() || $cap < 1) {
            return [];
        }

        return array_slice(
            Cache::remember(self::CACHE_KEY, now()->addMinutes(2), fn (): array => $this->build()),
            0,
            $cap,
        );
    }

    public function resolve(?string $id): string
    {
        [$therapistId, $date] = explode('|', (string) $id, 2);

        ($this->notifier)($therapistId, $date);

        return 'Čekající na tento den byli upozorněni.';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function build(): array
    {
        $groups = ReservationDayWaitlistEntry::query()
            ->whereNull('notified_at')
            // An "any therapist" entry has nobody's day to check and no link to
            // offer, so it stays on the list page only.
            ->whereNotNull('therapist_id')
            ->whereDate('reservation_date', '>=', today())
            ->with('therapist.user')
            ->orderBy('reservation_date')
            ->get()
            ->groupBy(fn (ReservationDayWaitlistEntry $entry): string => $entry->therapist_id.'|'.$entry->reservation_date->toDateString())
            ->take(self::MAX_PAIRS);

        $cards = [];

        foreach ($groups as $key => $entries) {
            [$therapistId, $date] = explode('|', (string) $key, 2);

            if (! $this->slots->therapistHasOpening($therapistId, Carbon::parse($date))) {
                continue;
            }

            $cards[] = $this->card($key, $date, $entries);
        }

        return $cards;
    }

    /**
     * @param  Collection<int, ReservationDayWaitlistEntry>  $entries
     * @return array<string, mixed>
     */
    private function card(string $key, string $date, Collection $entries): array
    {
        $waiting = $entries->count();
        $therapist = $entries->first()->therapistLabel();
        $day = Carbon::parse($date);

        return Suggestion::make(
            type: $this->type(),
            group: SuggestionGroup::Rezervace,
            tone: 'success',
            icon: 'heroicon-m-bell-alert',
            title: 'Uvolnil se termín pro pořadník',
            detail: "{$therapist} · {$day->format('d.m.Y')} — čeká zájemců: {$waiting}. Můžete je upozornit.",
            url: ReservationDayWaitlistResource::getUrl('index', [
                'filters' => ['reservation_date' => ['value' => $date]],
            ]),
            priority: 20,
            id: $key,
            meta: $day->format('d.m.'),
            resolveLabel: 'Upozornit',
            resolveConfirm: 'Všem čekajícím na tento den přijde e-mail, že se termín uvolnil.',
            fingerprint: "waiting:{$waiting}",
            sortKey: $date,
        );
    }
}
