<?php

namespace App\Livewire\Zone;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Platby": the client's payment history with filters, on-demand invoice
 * downloads for settled payments and a "Zaplatit" modal for open ones
 * (pencil frame Profile/My Payments).
 */
class Payments extends Component
{
    use WithPagination;

    #[Url(as: 'rok')]
    public ?string $year = null;

    #[Url(as: 'stav')]
    public ?string $status = null;

    /**
     * Deep-linkable so the "Zaplatit" modal can be opened straight from a
     * course card or e-mail via ?platba={id}.
     */
    #[Url(as: 'platba')]
    public ?string $payingId = null;

    public function updatedYear(): void
    {
        $this->resetPage('strana');
    }

    public function updatedStatus(): void
    {
        $this->resetPage('strana');
    }

    public function openPayment(string $paymentId): void
    {
        $this->payingId = $paymentId;
    }

    public function closePayment(): void
    {
        $this->payingId = null;
    }

    /**
     * Cash is settled in person; a client who would rather send the money over
     * switches the debt to a bank transfer here. PaymentObserver ignores a
     * method-only change, so nothing fires — and the auto-PPD that a cash
     * payment would have triggered on settlement no longer applies.
     */
    public function switchToTransfer(): void
    {
        $payment = $this->payingPayment();

        if ($payment === null
            || $payment->status === PaymentStatus::Paid
            || $payment->method !== PaymentMethod::Cash) {
            return;
        }

        $payment->update(['method' => PaymentMethod::Qr]);
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.zone.payments', [
            'paying' => $this->payingPayment(),
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

    /**
     * Always resolved through the client's own relation — an id coming off the
     * wire can never reach someone else's payment.
     */
    protected function payingPayment(): ?Payment
    {
        if ($this->payingId === null) {
            return null;
        }

        return $this->user()->payments()->whereKey($this->payingId)->first();
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
