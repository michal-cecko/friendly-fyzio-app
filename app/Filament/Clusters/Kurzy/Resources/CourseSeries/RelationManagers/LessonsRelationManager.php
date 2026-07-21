<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\RelationManagers;

use App\Filament\Clusters\Kurzy\Resources\CourseLessons\CourseLessonResource;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Schemas\CourseLessonForm;
use App\Filament\Support\Concerns\NotifiesScheduleChange;
use App\Models\CourseLesson;
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
 * notification ({@see NotifiesScheduleChange}) still fires.
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
        return CourseLessonForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('lesson_date')
            ->modifyQueryUsing(fn ($query) => $query->with(['instructor', 'room']))
            ->columns([
                TextColumn::make('lesson_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Od'),
                TextColumn::make('end_time')
                    ->label('Do'),
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
                    ->url(fn (CourseLesson $record): string => CourseLessonResource::getUrl('view', ['record' => $record])),
                Action::make('edit')
                    ->label('Upravit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (CourseLesson $record): string => CourseLessonResource::getUrl('edit', ['record' => $record])),
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
