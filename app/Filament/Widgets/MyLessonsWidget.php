<?php

namespace App\Filament\Widgets;

use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Support\Tables\OccupancyColumn;
use App\Filament\Widgets\Concerns\OwnWork;
use App\Models\Lesson;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The lecturer's own next lessons — the personal twin of
 * {@see UpcomingLessonsWidget}, covering course lessons and standalone events
 * alike since both are the same record.
 *
 * Rows open the lesson, where the Docházka roster answers the question a lecturer
 * actually arrives with: who is coming (FSS :245). The instructor column is gone
 * — every row is theirs — and occupancy takes its place.
 */
class MyLessonsWidget extends TableWidget
{
    use OwnWork;

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 1;

    /**
     * Teaching is the Lecturer capability's whole purpose, so a therapist who
     * also runs a course is expected to hold it.
     */
    public static function canView(): bool
    {
        return (bool) auth()->user()?->isLecturer();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Moje nejbližší lekce')
            ->headerActions([
                Action::make('openLessons')
                    ->label('Všechny moje lekce')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('gray')
                    ->url(LessonResource::getUrl('index')),
            ])
            ->query(
                Lesson::query()
                    ->where('instructor_id', $this->ownUserId())
                    ->where(function (Builder $query): void {
                        $query->whereDate('lesson_date', '>', today())
                            ->orWhere(fn (Builder $today): Builder => $today
                                ->whereDate('lesson_date', today())
                                ->where('end_time', '>=', now()->format('H:i:s')));
                    })
                    ->withOccupancyCounts()
                    ->with(['series.course', 'category', 'room'])
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
                TextColumn::make('room.name')
                    ->label('Místnost')
                    ->placeholder('—'),
            ])
            ->recordUrl(fn (Lesson $record): string => LessonResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->emptyStateHeading('Žádné nadcházející lekce')
            ->emptyStateIcon(Heroicon::OutlinedSparkles);
    }
}
