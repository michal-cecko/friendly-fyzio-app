<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses\RelationManagers;

use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Schemas\CourseSeriesForm;
use App\Models\CourseSeries;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The course's runs ("série"), managed inline on the course detail page. Each row
 * deep-links to the série's own view page, where its lessons and enrollments live.
 */
class SeriesRelationManager extends RelationManager
{
    protected static string $relationship = 'series';

    protected static ?string $title = 'Série';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedRectangleStack;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return CourseSeriesForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable(),
                TextColumn::make('start_date')
                    ->label('Začátek')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Konec')
                    ->date('d.m.Y'),
                TextColumn::make('active_takers_count')
                    ->label('Obsazenost')
                    ->counts('activeTakers')
                    ->state(fn (CourseSeries $record): string => $record->takenSpots().' / '.$record->capacity)
                    ->description(fn (CourseSeries $record): ?string => $record->isFull() ? 'Plně obsazeno' : null),
                TextColumn::make('price')
                    ->label('Cena')
                    ->suffix(' Kč'),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('visibility')
                    ->label('Viditelnost')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Přidat sérii')
                    ->modalHeading('Přidat sérii'),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (CourseSeries $record): string => CourseSeriesResource::getUrl('view', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Zatím žádné série')
            ->emptyStateDescription('Přidejte sérii kurzu s termínem, cenou a kapacitou.');
    }
}
