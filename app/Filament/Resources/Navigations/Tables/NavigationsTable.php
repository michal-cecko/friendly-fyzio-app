<?php

namespace App\Filament\Resources\Navigations\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NavigationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('location')
                    ->label('Umístění')
                    ->badge(),
                TextColumn::make('items_count')
                    ->label('Počet položek')
                    ->counts('items'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
