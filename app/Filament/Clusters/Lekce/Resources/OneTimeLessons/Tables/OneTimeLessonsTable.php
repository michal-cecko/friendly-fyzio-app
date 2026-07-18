<?php

namespace App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Tables;

use App\Filament\Support\Tables\TimestampColumns;
use App\Models\OneTimeLesson;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OneTimeLessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.name')
                    ->label('Kurz')
                    ->sortable(),
                TextColumn::make('lesson_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Od'),
                TextColumn::make('instructor.name')
                    ->label('Lektor')
                    ->toggleable(),
                TextColumn::make('room.name')
                    ->label('Místnost')
                    ->toggleable(),
                TextColumn::make('active_takers_count')
                    ->label('Obsazenost')
                    ->counts('activeTakers')
                    ->state(fn (OneTimeLesson $record): string => $record->takenSpots().' / '.$record->capacity)
                    ->description(fn (OneTimeLesson $record): ?string => $record->isFull() ? 'Plně obsazeno' : null),
                TextColumn::make('price')
                    ->label('Cena')
                    ->suffix(' Kč'),
                TextColumn::make('published_at')
                    ->label('Publikováno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Koncept')
                    ->toggleable(),
                ...TimestampColumns::make(),
            ])
            ->defaultSort('lesson_date', 'desc')
            ->filters([
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
