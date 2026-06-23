<?php

namespace App\Filament\Clusters\Provoz\Resources\Services\Tables;

use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
use App\Filament\Support\Tables\TimestampColumns;
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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Kategorie')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('category.type')
                    ->label('Typ')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('duration_minutes')
                    ->label('Délka')
                    ->suffix(' min')
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Cena')
                    ->numeric()
                    ->suffix(' Kč')
                    ->sortable(),
                TextColumn::make('visibility')
                    ->label('Viditelnost')
                    ->badge()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Publikováno')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Typ')
                    ->options(ServiceType::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('type', $data['value']))
                        : $query),
                SelectFilter::make('visibility')
                    ->label('Viditelnost')
                    ->options(ServiceVisibility::class),
                SelectFilter::make('category')
                    ->label('Kategorie')
                    ->relationship('category', 'name')
                    ->preload(),
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
