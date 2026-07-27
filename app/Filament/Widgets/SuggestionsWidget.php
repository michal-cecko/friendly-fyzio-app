<?php

namespace App\Filament\Widgets;

use App\Filament\Support\Concerns\InteractsWithSuggestions;
use App\Filament\Widgets\Concerns\AdminOrStaff;
use App\Support\Suggestions\SuggestionFinder;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;

/**
 * The dashboard twin of the Návrhy page: the five most urgent things waiting
 * for a decision, next to Problémy — what is undone beside what is wrong.
 */
class SuggestionsWidget extends Widget implements HasActions, HasSchemas
{
    use AdminOrStaff;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithSuggestions;

    /** Cards shown here; the rest live on the Návrhy page. */
    private const LIMIT = 5;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.suggestions';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'suggestions' => SuggestionFinder::top(self::LIMIT),
            'total' => SuggestionFinder::count(),
        ];
    }
}
