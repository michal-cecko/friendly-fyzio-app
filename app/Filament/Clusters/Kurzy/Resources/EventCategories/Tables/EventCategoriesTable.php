<?php

namespace App\Filament\Clusters\Kurzy\Resources\EventCategories\Tables;

use App\Filament\Support\Actions\OpenPublicPageAction;
use App\Filament\Support\Tables\TimestampColumns;
use App\Models\EventCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('display_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('display_order')
                    ->label('Pořadí')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('events_count')
                    ->label('Akcí')
                    ->counts('events'),
                TextColumn::make('published_at')
                    ->label('Stav')
                    ->badge()
                    ->state(fn (EventCategory $record): string => $record->isPublished() ? 'Veřejné' : 'Skryté')
                    ->color(fn (EventCategory $record): string => $record->isPublished() ? 'success' : 'gray')
                    ->sortable(),
                ...TimestampColumns::make(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                OpenPublicPageAction::make(),
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
