<?php

namespace App\Support\Suggestions\Rules;

use App\Enums\SuggestionGroup;
use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Support\Payments\PastDue;
use App\Support\StaffScope;
use App\Support\Suggestions\Suggestion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Issued invoices nobody has paid by their due date. Office work, so it never
 * shows on a therapist's own list.
 */
class PastDueInvoicesRule extends AggregateRule
{
    public function type(): string
    {
        return 'invoices_past_due';
    }

    public function isEnabled(): bool
    {
        return ! StaffScope::current()->isScoped();
    }

    protected function query(): Builder
    {
        return PastDue::invoices(Invoice::query());
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
            icon: 'heroicon-m-document-text',
            title: 'Faktury po splatnosti',
            detail: "Nezaplacených faktur po splatnosti: {$count}. Připomeňte je klientům.",
            url: InvoiceResource::getUrl('index', [
                'filters' => ['past_due' => ['isActive' => true]],
            ]),
            priority: 55,
            snoozeOnDismiss: true,
        )];
    }
}
