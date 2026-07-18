<?php

namespace App\Filament\Clusters\Workshopy\Resources\Workshops\Tables;

use App\Filament\Support\Tables\TimestampColumns;
use App\Models\Workshop;
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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class WorkshopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('workshop_date')
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
                    ->state(fn (Workshop $record): string => $record->takenSpots().' / '.$record->capacity)
                    ->description(fn (Workshop $record): ?string => $record->isFull() ? 'Plně obsazeno' : null),
                TextColumn::make('price')
                    ->label('Cena')
                    ->suffix(' Kč'),
                TextColumn::make('published_at')
                    ->label('Publikováno')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
                ...TimestampColumns::make(),
            ])
            ->defaultSort('workshop_date', 'desc')
            ->filters([
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
