<?php

namespace App\Filament\Clusters\Obsah\Resources\Banners\Tables;

use App\Enums\BannerType;
use App\Filament\Support\Tables\TimestampColumns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BannersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->sortable(),
                TextColumn::make('placement')
                    ->label('Umístění')
                    ->formatStateUsing(fn (string $state): string => $state === 'all' ? 'Všechny' : 'Konkrétní')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label('Aktivní')
                    ->boolean(),
                TextColumn::make('active_from')
                    ->label('Od')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('active_to')
                    ->label('Do')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->label('Priorita')
                    ->sortable(),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Typ')
                    ->options(BannerType::class),
                TernaryFilter::make('is_active')
                    ->label('Aktivní'),
                TrashedFilter::make(),
            ])
            ->recordActions([
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
