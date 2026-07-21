<?php

namespace App\Filament\Clusters\Provoz\Resources\StaffProfiles\Tables;

use App\Filament\Support\Tables\TimestampColumns;
use App\Models\StaffProfile;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StaffProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular()
                    ->getStateUsing(fn (StaffProfile $record): ?string => Media::url($record->photo, 'thumb')),
                TextColumn::make('user.name')
                    ->label('Jméno')
                    ->state(fn (StaffProfile $record): ?string => $record->user?->full_name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Pozice')
                    ->toggleable(),
                IconColumn::make('published')
                    ->label('Publikováno')
                    ->boolean()
                    ->getStateUsing(fn (StaffProfile $record): bool => $record->isPublished()),
                TextColumn::make('display_order')
                    ->label('Pořadí')
                    ->sortable()
                    ->toggleable(),
                ...TimestampColumns::make(),
            ])
            ->defaultSort('display_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
