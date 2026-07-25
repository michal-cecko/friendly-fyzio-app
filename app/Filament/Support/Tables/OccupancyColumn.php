<?php

namespace App\Filament\Support\Tables;

use App\Filament\Support\Schemas\OccupancyEntry;
use App\Models\Concerns\HasCapacity;
use App\Models\CourseLesson;
use Filament\Tables\Columns\ViewColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * The "Obsazenost" column shared by every enrollable offer (course series and
 * one-off events) and by individual course lessons: a progress bar that fills as
 * spots are taken, with the free spots out of the total shown above it. Records
 * must expose a `capacity` and a `takenSpots()` — either via {@see HasCapacity}
 * or, for lessons, via {@see CourseLesson}'s own spot accounting.
 *
 * The infolist twin is {@see OccupancyEntry}; both render the same bar and share
 * {@see state()}.
 */
class OccupancyColumn
{
    /**
     * Share of taken spots at which the ring stops reading as "plenty of room".
     */
    private const COMFORTABLE_UP_TO_PERCENT = 40;

    /**
     * Share of taken spots above which the ring warns that it is nearly full.
     */
    private const FILLING_UP_TO_PERCENT = 80;

    /**
     * @param  string|null  $countsRelationship  Relation the table should count into the
     *                                           record for free; pass null when the model
     *                                           eager-loads its own counts (course lessons).
     */
    public static function make(string $name = 'active_takers_count', ?string $countsRelationship = 'activeTakers'): ViewColumn
    {
        $column = ViewColumn::make($name)
            ->label('Obsazenost')
            ->view('filament.tables.columns.occupancy')
            ->state(fn (Model $record): array => static::state($record))
            ->tooltip(fn (Model $record): string => static::tooltip($record));

        return $countsRelationship !== null
            ? $column->counts($countsRelationship)
            : $column;
    }

    public static function tooltip(Model $record): string
    {
        return 'Obsazeno '.$record->takenSpots().' z '.(int) $record->capacity;
    }

    /**
     * @return array{free: int, taken: int, capacity: int, percent: int, tone: string}
     */
    public static function state(Model $record): array
    {
        $capacity = max(0, (int) $record->capacity);
        $taken = min($record->takenSpots(), $capacity);
        $free = $capacity - $taken;
        $percent = $capacity > 0 ? (int) round($taken / $capacity * 100) : 0;

        return [
            'free' => $free,
            'taken' => $taken,
            'capacity' => $capacity,
            'percent' => $percent,
            'tone' => static::tone($capacity, $percent),
        ];
    }

    private static function tone(int $capacity, int $percent): string
    {
        return match (true) {
            $capacity === 0 => 'empty',
            $percent <= self::COMFORTABLE_UP_TO_PERCENT => 'comfortable',
            $percent <= self::FILLING_UP_TO_PERCENT => 'filling',
            default => 'tight',
        };
    }
}
