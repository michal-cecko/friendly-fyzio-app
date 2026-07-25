<?php

namespace App\Filament\Clusters\Provoz\Resources\Rooms\Tables;

use App\Filament\Support\Tables\TimestampColumns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('short_name')
                    ->label('Zkratka')
                    ->badge()
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('building.name')
                    ->label('Budova')
                    ->sortable(),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('building')
                    ->label('Budova')
                    ->relationship('building', 'name')
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
