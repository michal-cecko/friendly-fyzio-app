<?php

namespace App\Filament\Clusters\Provoz\Resources\TherapistProfiles\Tables;

use App\Filament\Support\Tables\TimestampColumns;
use App\Models\TherapistProfile;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TherapistProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular()
                    ->getStateUsing(fn (TherapistProfile $record): ?string => Media::url($record->photo, 'thumb')),
                TextColumn::make('user.name')
                    ->label('Jméno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Pozice')
                    ->toggleable(),
                IconColumn::make('published')
                    ->label('Publikováno')
                    ->boolean()
                    ->getStateUsing(fn (TherapistProfile $record): bool => $record->isPublished()),
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
