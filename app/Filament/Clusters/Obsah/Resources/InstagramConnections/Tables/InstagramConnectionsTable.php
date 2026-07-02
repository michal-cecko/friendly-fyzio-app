<?php

namespace App\Filament\Clusters\Obsah\Resources\InstagramConnections\Tables;

use App\Enums\InstagramConnectionStatus;
use App\Filament\Clusters\Obsah\Resources\InstagramConnections\InstagramConnectionResource;
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

class InstagramConnectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label('Účet')
                    ->formatStateUsing(fn (?string $state): string => $state ? '@'.$state : 'Nepřipojeno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->sortable(),
                TextColumn::make('posts_count')
                    ->label('Příspěvky')
                    ->counts('posts')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktivní')
                    ->boolean(),
                TextColumn::make('last_synced_at')
                    ->label('Synchronizováno')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('token_expires_at')
                    ->label('Token do')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(InstagramConnectionStatus::class),
                TernaryFilter::make('is_active')
                    ->label('Aktivní'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                InstagramConnectionResource::authorizeAction(),
                InstagramConnectionResource::syncAction(),
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
