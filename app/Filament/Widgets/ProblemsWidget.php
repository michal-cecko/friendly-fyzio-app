<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AdminOrStaff;
use App\Support\Reservations\Conflict;
use App\Support\Reservations\ConflictFinder;
use App\Support\StaffScope;
use Filament\Widgets\Widget;

class ProblemsWidget extends Widget
{
    use AdminOrStaff;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.problems';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $conflicts = StaffScope::current()->filterConflicts(ConflictFinder::upcoming(30));

        $hard = array_values(array_filter($conflicts, fn (Conflict $conflict): bool => $conflict->isHard()));
        $soft = ConflictFinder::collapseRecurring(
            array_values(array_filter($conflicts, fn (Conflict $conflict): bool => ! $conflict->isHard()))
        );

        // Real conflicts first — the expected overlaps only fill the remaining
        // room, so a wall of recurring blockings can never push one off the card.
        $ordered = [...$hard, ...$soft];

        // Four fills the card's two-column grid in whole rows — the widget shares
        // its row with Návrhy, so it is half the dashboard wide.
        return [
            'problems' => array_slice($ordered, 0, 4),
            'total' => count($ordered),
        ];
    }
}
