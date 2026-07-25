<?php

namespace App\Filament\Support\Schemas;

use App\Filament\Support\Tables\OccupancyColumn;
use Filament\Infolists\Components\ViewEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * The infolist twin of {@see OccupancyColumn}: the same "Obsazenost" progress bar
 * on a detail page, reading free spots out of the total. Occupancy is derived
 * from the record itself (`capacity` + `takenSpots()`), so it works for course
 * séries, one-off events and individual lessons alike.
 */
class OccupancyEntry
{
    public static function make(string $name = 'occupancy'): ViewEntry
    {
        return ViewEntry::make($name)
            ->label('Obsazenost')
            ->view('filament.infolists.entries.occupancy')
            ->state(fn (Model $record): array => OccupancyColumn::state($record))
            ->tooltip(fn (Model $record): string => OccupancyColumn::tooltip($record));
    }
}
