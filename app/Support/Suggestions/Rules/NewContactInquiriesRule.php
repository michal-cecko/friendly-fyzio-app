<?php

namespace App\Support\Suggestions\Rules;

use App\Enums\ContactInquiryStatus;
use App\Enums\SuggestionGroup;
use App\Filament\Resources\ContactInquiries\ContactInquiryResource;
use App\Models\ContactInquiry;
use App\Support\StaffScope;
use App\Support\Suggestions\Suggestion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Messages from the web form nobody has picked up. The topbar envelope already
 * counts them ambiently; here they are a work item with a deadline feel.
 */
class NewContactInquiriesRule extends AggregateRule
{
    public function type(): string
    {
        return 'contact_inquiries_new';
    }

    public function isEnabled(): bool
    {
        return ! StaffScope::current()->isScoped();
    }

    protected function query(): Builder
    {
        return ContactInquiry::query()->where('status', ContactInquiryStatus::New);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function card(int $count): array
    {
        return [Suggestion::make(
            type: $this->type(),
            group: SuggestionGroup::Obsah,
            tone: 'warning',
            icon: 'heroicon-m-envelope',
            title: 'Nové zprávy z webu',
            detail: "Nevyřízených zpráv z kontaktního formuláře: {$count}.",
            url: ContactInquiryResource::getUrl('index', [
                'filters' => ['status' => ['value' => ContactInquiryStatus::New->value]],
            ]),
            priority: 70,
            snoozeOnDismiss: true,
        )];
    }
}
