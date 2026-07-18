<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Tables;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Filament\Support\Tables\TimestampColumns;
use App\Models\CourseSeries;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CourseSeriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.name')
                    ->label('Kurz')
                    ->sortable(),
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
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(CourseSeriesStatus::class),
                SelectFilter::make('visibility')
                    ->label('Viditelnost')
                    ->options(CourseSeriesVisibility::class),
                SelectFilter::make('course')
                    ->label('Kurz')
                    ->relationship('course', 'name')
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
