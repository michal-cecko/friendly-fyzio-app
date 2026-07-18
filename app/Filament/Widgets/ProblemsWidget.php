<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AdminOnly;
use App\Support\Reservations\ConflictFinder;
use Filament\Widgets\Widget;

class ProblemsWidget extends Widget
{
    use AdminOnly;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.problems';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $conflicts = ConflictFinder::upcoming(30);

        return [
            'problems' => array_slice($conflicts, 0, 5),
            'total' => count($conflicts),
        ];
    }
}
