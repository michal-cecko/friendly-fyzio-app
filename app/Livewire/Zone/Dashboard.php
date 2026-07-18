<?php

namespace App\Livewire\Zone;

use App\Enums\ReservationStatus;
use App\Models\User;
use App\Support\Credits\CreditLedger;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The client-zone landing page: the next few reservations, active substitute
 * entries and the credit balance at a glance (pencil frame Profile/Dashboard).
 */
class Dashboard extends Component
{
    public function render(): View
    {
        $user = $this->user();

        return view('livewire.zone.dashboard', [
            'firstName' => str($user->name)->before(' ')->toString() ?: $user->name,
            'upcoming' => $user->reservations()
                ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
                ->whereDate('reservation_date', '>=', today())
                ->orderBy('reservation_date')
                ->orderBy('start_time')
                ->with(['service', 'therapist.user', 'payments'])
                ->limit(3)
                ->get(),
            'tokens' => $user->substituteTokens()
                ->whereNull('used_at')
                ->where('expires_at', '>=', now())
                ->with('sourceLesson.series.course')
                ->orderBy('expires_at')
                ->limit(3)
                ->get(),
            'creditBalance' => CreditLedger::balanceFor($user),
            'creditExpiry' => CreditLedger::nearestExpiry($user),
            'lastCreditChange' => $user->creditTransactions()->latest()->value('created_at'),
        ]);
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
