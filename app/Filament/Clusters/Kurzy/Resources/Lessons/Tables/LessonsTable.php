<?php

namespace App\Filament\Clusters\Kurzy\Resources\Lessons\Tables;

use App\Filament\Support\Tables\OccupancyColumn;
use App\Filament\Support\Tables\TimestampColumns;
use App\Models\Lesson;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->state(fn (Lesson $record): string => $record->displayName())
                    ->description(fn (Lesson $record): ?string => $record->series?->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Kategorie')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('lesson_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Od')
                    ->time('H:i'),
                TextColumn::make('series.course.name')
                    ->label('Kurz')
                    ->state(fn (Lesson $record): ?string => $record->offerCourse()?->name)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('instructor.name')
                    ->label('Lektor')
                    ->toggleable(),
                TextColumn::make('room.name')
                    ->label('Místnost')
                    ->toggleable(),
                OccupancyColumn::make('occupancy', countsRelationship: null),
                TextColumn::make('price')
                    ->label('Cena')
                    ->state(fn (Lesson $record): ?int => $record->price)
                    ->suffix(' Kč')
                    ->placeholder('—'),
                TextColumn::make('published_at')
                    ->label('Publikováno')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
                ...TimestampColumns::make(),
            ])
            ->defaultSort('lesson_date', 'desc')
            ->filters([
                SelectFilter::make('series')
                    ->label('Série')
                    ->relationship('series', 'name')
                    ->preload(),
                SelectFilter::make('event_category_id')
                    ->label('Kategorie')
                    ->relationship('category', 'name')
                    ->preload(),
                TernaryFilter::make('kind')
                    ->label('Druh')
                    ->placeholder('Vše')
                    ->trueLabel('Lekce kurzu')
                    ->falseLabel('Samostatné')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('series_id'),
                        false: fn (Builder $query) => $query->whereNull('series_id'),
                        blank: fn (Builder $query) => $query,
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
