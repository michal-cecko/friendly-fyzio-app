<?php

namespace App\Support\Suggestions\Rules;

use App\Enums\SuggestionGroup;
use App\Filament\Clusters\Obsah\Resources\Reviews\ReviewResource;
use App\Models\Review;
use App\Support\StaffScope;
use App\Support\Suggestions\Suggestion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reviews that were written but never made visible on the site. A review from
 * the magic-link form is created hidden on purpose, so this is the queue where
 * somebody decides whether it goes public.
 */
class HiddenReviewsRule extends AggregateRule
{
    public function type(): string
    {
        return 'reviews_hidden';
    }

    public function isEnabled(): bool
    {
        return ! StaffScope::current()->isScoped();
    }

    protected function query(): Builder
    {
        return Review::query()->where('visible', false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function card(int $count): array
    {
        return [Suggestion::make(
            type: $this->type(),
            group: SuggestionGroup::Obsah,
            tone: 'info',
            icon: 'heroicon-m-star',
            title: 'Recenze čekají na schválení',
            detail: "Napsaných, ale nezveřejněných recenzí: {$count}.",
            url: ReviewResource::getUrl('index', [
                'filters' => ['visible' => ['value' => '0']],
            ]),
            priority: 80,
            snoozeOnDismiss: true,
        )];
    }
}
