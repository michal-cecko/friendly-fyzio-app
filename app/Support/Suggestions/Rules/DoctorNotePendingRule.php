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
 * A client promised a doctor's note for a late cancellation and nobody has
 * decided yet whether it arrived — the storno fee is neither waived nor
 * charged. Resolved one reservation at a time with the "Doporučení lékaře"
 * action, so the card only points at the filtered list.
 */
class DoctorNotePendingRule extends AggregateRule
{
    public function type(): string
    {
        return 'doctor_note_pending';
    }

    protected function query(): Builder
    {
        $scope = StaffScope::current();

        return ReservationMetrics::scopeDoctorNotePending(Reservation::query())
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
            icon: 'heroicon-m-document-text',
            title: 'Nevyřízené doporučení lékaře',
            detail: "Klientů, kteří slíbili doporučení: {$count}. Rozhodněte, zda storno poplatek prominout, nebo vyúčtovat.",
            url: ReservationResource::getUrl('index', [
                'filters' => ['doctor_note_pending' => ['isActive' => true]],
            ]),
            priority: 65,
            snoozeOnDismiss: true,
        )];
    }
}
