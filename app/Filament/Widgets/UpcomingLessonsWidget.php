<?php

namespace App\Filament\Widgets;

use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Support\Tables\OccupancyColumn;
use App\Filament\Widgets\Concerns\AdminOnly;
use App\Models\Lesson;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The lesson twin of {@see UpcomingReservationsWidget}: what is happening in the
 * rooms today and next, both the lessons of a course série and the standalone
 * workshops — one list, since both are the same record.
 */
class UpcomingLessonsWidget extends TableWidget
{
    use AdminOnly;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Nejbližší lekce')
            ->headerActions([
                Action::make('openLessons')
                    ->label('Všechny lekce')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('gray')
                    ->url(LessonResource::getUrl('index')),
            ])
            ->query(
                Lesson::query()
                    ->where(function (Builder $query): void {
                        $query->whereDate('lesson_date', '>', today())
                            ->orWhere(fn (Builder $today): Builder => $today
                                ->whereDate('lesson_date', today())
                                ->where('end_time', '>=', now()->format('H:i:s')));
                    })
                    ->withOccupancyCounts()
                    ->with(['series.course', 'category', 'instructor', 'room'])
                    ->orderBy('lesson_date')
                    ->orderBy('start_time')
                    ->limit(5),
            )
            ->columns([
                TextColumn::make('lesson_date')
                    ->label('Kdy')
                    ->formatStateUsing(fn (Lesson $record): string => $record->lesson_date->format('d.m.')
                        .' '.substr((string) $record->start_time, 0, 5)
                        .'–'.substr((string) $record->end_time, 0, 5))
                    ->weight('semibold'),
                TextColumn::make('name')
                    ->label('Lekce')
                    ->state(fn (Lesson $record): string => $record->displayName())
                    ->description(fn (Lesson $record): string => $record->series?->name
                        ?? $record->category?->name
                        ?? 'Samostatná akce')
                    ->limit(28),
                OccupancyColumn::make('occupancy', countsRelationship: null),
                TextColumn::make('instructor.name')
                    ->label('Lektor')
                    ->description(fn (Lesson $record): ?string => $record->room?->name)
                    ->placeholder('—'),
            ])
            ->recordUrl(fn (Lesson $record): string => LessonResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->emptyStateHeading('Žádné nadcházející lekce')
            ->emptyStateIcon(Heroicon::OutlinedSparkles);
    }
}
