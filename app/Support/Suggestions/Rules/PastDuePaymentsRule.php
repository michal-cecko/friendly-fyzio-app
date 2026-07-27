<?php

namespace App\Support\Suggestions\Rules;

use App\Enums\SuggestionGroup;
use App\Filament\Clusters\Finance\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Support\Payments\PastDue;
use App\Support\StaffScope;
use App\Support\Suggestions\Suggestion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Payment requests whose due date passed with no money in. Chasing them is
 * office work, so this never shows on a therapist's own list.
 */
class PastDuePaymentsRule extends AggregateRule
{
    public function type(): string
    {
        return 'payments_past_due';
    }

    public function isEnabled(): bool
    {
        return ! StaffScope::current()->isScoped();
    }

    protected function query(): Builder
    {
        return PastDue::payments(Payment::query());
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function card(int $count): array
    {
        return [Suggestion::make(
            type: $this->type(),
            group: SuggestionGroup::Platby,
            tone: 'danger',
            icon: 'heroicon-m-banknotes',
            title: 'Platby po splatnosti',
            detail: "Neuhrazených plateb po splatnosti: {$count}. Ověřte je a označte jako zaplacené, nebo připomeňte.",
            url: PaymentResource::getUrl('index', [
                'filters' => ['past_due' => ['isActive' => true]],
            ]),
            priority: 50,
            snoozeOnDismiss: true,
        )];
    }
}
