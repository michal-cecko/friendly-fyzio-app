<?php

namespace App\Support\Suggestions\Rules;

use App\Enums\SuggestionGroup;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Support\Reservations\ReservationMetrics;
use App\Support\StaffScope;
use App\Support\Suggestions\Suggestion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Visits that happened without anyone writing them up — the design brief's
 * "Pending Notes", surfaced as a suggestion rather than a dashboard widget so it
 * arrives with dismissals, the topbar badge and the Návrhy page for free.
 *
 * Scoped like {@see UnsettledPastVisitsRule}: a therapist is nudged about their
 * own visits, an administrator about the clinic's. The same two guards apply —
 * imported visits are skipped, since their history has no notes by construction,
 * and the window stops at three months because older gaps are never going to be
 * filled in from memory.
 */
class MissingVisitNoteRule extends AggregateRule
{
    /** How far back a missing note is still worth writing. */
    private const WINDOW_DAYS = 90;

    public function type(): string
    {
        return 'missing_visit_note';
    }

    protected function query(): Builder
    {
        $scope = StaffScope::current();

        return ReservationMetrics::scopeMissingVisitNote(Reservation::query())
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
            tone: 'info',
            icon: 'heroicon-m-pencil-square',
            title: 'Návštěvy bez poznámky z terapie',
            detail: "Za poslední tři měsíce chybí poznámka u návštěv: {$count}.",
            url: ReservationResource::getUrl('index', [
                'filters' => ['missing_note' => ['isActive' => true]],
            ]),
            priority: 62,
            snoozeOnDismiss: true,
        )];
    }
}
