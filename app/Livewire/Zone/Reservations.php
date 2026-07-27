<?php

namespace App\Livewire\Zone;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Reservations\ClientReservationState;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Moje rezervace": the active and finished tabs (pencil frame Profile/My
 * Reservations). Rows link to the detail; the badge shows the derived
 * customer-facing state.
 *
 * "Aktivní" is not simply "upcoming" — a reservation the client still has to act
 * on stays here no matter how far in the past it is. A late storno awaiting a
 * doctor's note or an unpaid fee must never quietly drop into the archive.
 */
class Reservations extends Component
{
    public const TABS = [
        'aktivni' => 'Aktivní',
        'dokoncene' => 'Dokončené',
    ];

    /**
     * Values used by the two-tab version that shipped before ("Nadcházející" /
     * "Minulé"), so bookmarked and e-mailed links keep resolving.
     *
     * @var array<string, string>
     */
    private const LEGACY_TABS = [
        'nadchazejici' => 'aktivni',
        'minule' => 'dokoncene',
    ];

    #[Url(as: 'zalozka')]
    public string $tab = 'aktivni';

    public function mount(): void
    {
        $this->tab = $this->normaliseTab($this->tab);
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $this->normaliseTab($tab);
    }

    private function normaliseTab(string $tab): string
    {
        $tab = self::LEGACY_TABS[$tab] ?? $tab;

        return array_key_exists($tab, self::TABS) ? $tab : 'aktivni';
    }

    public function render(): View
    {
        $active = $this->tab === 'aktivni';

        $reservations = $this->user()->reservations()
            ->with(['service.cancellationRule', 'therapist.user', 'payments', 'doctorNoteDocuments'])
            ->when($active, fn (Builder $query) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where(fn (Builder $upcoming) => $upcoming
                        ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
                        ->whereDate('reservation_date', '>=', today()))
                    ->orWhere(fn (Builder $open) => $open->needsClientAttention()))
                ->orderBy('reservation_date')
                ->orderBy('start_time'))
            ->when(! $active, fn (Builder $query) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('status', ReservationStatus::Cancelled)
                    ->orWhereDate('reservation_date', '<', today()))
                // The inverse of the active tab's attention arm, so every reservation
                // lands in exactly one tab.
                ->whereNot(fn (Builder $inner) => $inner->needsClientAttention())
                ->orderByDesc('reservation_date')
                ->orderByDesc('start_time'))
            ->limit(50)
            ->get();

        // Grouped off the single fetch by the very state the badge shows, so the
        // highlighted box and the badges can never disagree. The SQL filter above is
        // a superset of this, which is what makes the two tabs disjoint.
        [$attention, $rest] = $reservations->partition(
            fn (Reservation $reservation): bool => ClientReservationState::for($reservation)->needsAttention(),
        );

        return view('livewire.zone.reservations', [
            'attention' => $this->sortAttention($attention),
            'reservations' => $rest->values(),
        ]);
    }

    /**
     * Newest first — the thing the client just cancelled is the thing they came for.
     *
     * @param  Collection<int, Reservation>  $attention
     * @return Collection<int, Reservation>
     */
    private function sortAttention(Collection $attention): Collection
    {
        return $attention
            ->sortByDesc(fn (Reservation $reservation) => $reservation->startsAt())
            ->values();
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
