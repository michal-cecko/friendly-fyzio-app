<?php

namespace App\Support\Suggestions\Rules;

use App\Enums\SuggestionGroup;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Support\Reservations\ConflictFinder;
use App\Support\Reservations\ReservationMetrics;
use App\Support\StaffScope;
use App\Support\Suggestions\Suggestion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Visits that happened but were never marked "Vybaveno" — the client came, and
 * whether they paid, did not show up, or still owe something was never closed.
 *
 * Two guards keep this honest: imported visits are skipped (their whole history
 * is unsettled by construction, exactly as {@see ConflictFinder}
 * skips them), and the window stops at three months, because older rows are
 * history nobody is going to work through. `reservations:settle-past` will sweep
 * most of this the day the schedule is switched on.
 */
class UnsettledPastVisitsRule extends AggregateRule
{
    /** How far back a visit is still considered worth settling. */
    private const WINDOW_DAYS = 90;

    public function type(): string
    {
        return 'unsettled_past_visits';
    }

    protected function query(): Builder
    {
        $scope = StaffScope::current();

        return ReservationMetrics::scopeUnsettledPast(Reservation::query())
            ->whereNull('imported_at')
            ->whereDate('reservation_date', '>=', today()->subDays(self::WINDOW_DAYS))
            ->when($scope->staffProfileId, fn (Builder $query, string $id) => $query->where('therapist_id', $id));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function card(int $count): array
    {
        return [Suggestion::make(
            type: $this->type(),
            group: SuggestionGroup::Rezervace,
            tone: 'warning',
            icon: 'heroicon-m-banknotes',
            title: 'Proběhlé návštěvy bez vybavení',
            detail: "Za poslední tři měsíce čeká na vybavení návštěv: {$count}.",
            url: ReservationResource::getUrl('index', [
                'filters' => ['unsettled_past' => ['isActive' => true]],
            ]),
            priority: 60,
            snoozeOnDismiss: true,
        )];
    }
}
