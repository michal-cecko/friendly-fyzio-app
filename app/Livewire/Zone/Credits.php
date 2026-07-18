<?php

namespace App\Livewire\Zone;

use App\Models\User;
use App\Support\Credits\CreditLedger;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Kredity": the balance banner with its nearest expiry plus the full ledger
 * history (pencil frame Profile/My Credit).
 */
class Credits extends Component
{
    use WithPagination;

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.zone.credits', [
            'balance' => CreditLedger::balanceFor($user),
            'expiry' => CreditLedger::nearestExpiry($user),
            'transactions' => $user->creditTransactions()
                ->latest()
                ->paginate(10, pageName: 'strana'),
        ]);
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
