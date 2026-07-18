<?php

namespace App\Livewire\Zone;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Platby": the client's payment history with filters and invoice downloads
 * (pencil frame Profile/My Payments).
 */
class Payments extends Component
{
    use WithPagination;

    #[Url(as: 'rok')]
    public ?string $year = null;

    #[Url(as: 'stav')]
    public ?string $status = null;

    public function updatedYear(): void
    {
        $this->resetPage('strana');
    }

    public function updatedStatus(): void
    {
        $this->resetPage('strana');
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.zone.payments', [
            'payments' => $user->payments()
                ->with('invoice')
                ->when($this->year, fn ($query, $year) => $query->whereYear('created_at', $year))
                ->when($this->status, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(10, pageName: 'strana'),
            // Few rows per client — deriving the year list in PHP keeps this
            // portable across sqlite (tests) and mysql.
            'years' => $user->payments()
                ->pluck('created_at')
                ->map(fn ($date): string => $date->format('Y'))
                ->unique()
                ->sortDesc()
                ->values(),
            'statuses' => PaymentStatus::cases(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
