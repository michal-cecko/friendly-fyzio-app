<?php

namespace App\Livewire\Zone;

use App\Enums\ReservationStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Moje rezervace": upcoming and past tabs (pencil frame Profile/My
 * Reservations). Rows link to the detail; the badge shows the derived
 * customer-facing state.
 */
class Reservations extends Component
{
    #[Url(as: 'zalozka')]
    public string $tab = 'nadchazejici';

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, ['nadchazejici', 'minule'], true) ? $tab : 'nadchazejici';
    }

    public function render(): View
    {
        $user = $this->user();

        $upcoming = $this->tab === 'nadchazejici';

        $reservations = $user->reservations()
            ->with(['service.cancellationRule', 'therapist.user', 'payments'])
            ->when($upcoming, fn ($query) => $query
                ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
                ->whereDate('reservation_date', '>=', today())
                ->orderBy('reservation_date')
                ->orderBy('start_time'))
            ->when(! $upcoming, fn ($query) => $query
                ->where(fn ($inner) => $inner
                    ->where('status', ReservationStatus::Cancelled)
                    ->orWhereDate('reservation_date', '<', today()))
                ->orderByDesc('reservation_date')
                ->orderByDesc('start_time'))
            ->limit(50)
            ->get();

        return view('livewire.zone.reservations', [
            'reservations' => $reservations,
        ]);
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
