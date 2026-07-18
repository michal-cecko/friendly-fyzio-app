<?php

namespace App\Livewire\Zone;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Faktury": the client's invoices with PDF downloads (pencil frame
 * Profile/Faktury). PDFs are rendered on demand, never stored.
 */
class Invoices extends Component
{
    use WithPagination;

    public function render(): View
    {
        return view('livewire.zone.invoices', [
            'invoices' => $this->user()->invoices()
                ->orderByDesc('issued_at')
                ->paginate(10, pageName: 'strana'),
        ]);
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
