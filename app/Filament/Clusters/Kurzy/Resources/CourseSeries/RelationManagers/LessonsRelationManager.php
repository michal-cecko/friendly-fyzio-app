<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\RelationManagers;

use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Schemas\LessonForm;
use App\Filament\Support\Concerns\PromptsScheduleChangeNotification;
use App\Filament\Support\Tables\OccupancyColumn;
use App\Models\Lesson;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The série's individual lessons, managed inline on the série detail page. Editing
 * deep-links to the lesson's own edit page so the participant schedule-change
 * prompt ({@see PromptsScheduleChangeNotification}) still fires.
 */
class LessonsRelationManager extends RelationManager
{
    protected static string $relationship = 'lessons';

    protected static ?string $title = 'Lekce';

    protected static ?string $recordTitleAttribute = 'lesson_date';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedCalendarDays;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return LessonForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('lesson_date')
            ->modelLabel('lekci')
            ->pluralModelLabel('lekce')
            ->modifyQueryUsing(fn ($query) => $query->with(['instructor', 'room'])->withOccupancyCounts())
            ->columns([
                TextColumn::make('lesson_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Od')
                    ->time('H:i'),
                TextColumn::make('end_time')
                    ->label('Do')
                    ->time('H:i'),
                OccupancyColumn::make('occupancy', countsRelationship: null),
                TextColumn::make('instructor.name')
                    ->label('Lektor')
                    ->placeholder('—'),
                TextColumn::make('room.name')
                    ->label('Místnost')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Přidat lekci')
                    ->modalHeading('Přidat lekci'),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Lesson $record): string => LessonResource::getUrl('view', ['record' => $record])),
                Action::make('edit')
                    ->label('Upravit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Lesson $record): string => LessonResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Zatím žádné lekce')
            ->emptyStateDescription('Přidejte jednotlivá setkání této série.');
    }
}
