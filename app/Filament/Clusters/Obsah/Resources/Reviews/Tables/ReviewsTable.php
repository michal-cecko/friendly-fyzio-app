<?php

namespace App\Filament\Clusters\Obsah\Resources\Reviews\Tables;

use App\Filament\Support\Tables\RecordLinkColumn;
use App\Filament\Support\Tables\TimestampColumns;
use App\Models\Review;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('reviewable'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('rating')
                    ->label('Hodnocení')
                    ->formatStateUsing(fn (?int $state): string => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state))
                    ->sortable(),
                TextColumn::make('author_name')
                    ->label('Autor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('content')
                    ->label('Text')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),
                RecordLinkColumn::make('reviewable', fn (Review $record): ?Model => $record->reviewable)
                    ->label('Vztahuje se k')
                    ->state(fn (Review $record): ?string => RecordLinkColumn::label($record->reviewable))
                    ->placeholder('Obecná')
                    ->wrap(),
                ToggleColumn::make('visible')
                    ->label('Zveřejněno'),
                ...TimestampColumns::make(),
            ])
            ->filters([
                TernaryFilter::make('visible')
                    ->label('Zveřejněno'),
                SelectFilter::make('rating')
                    ->label('Hodnocení')
                    ->options([
                        5 => '5 ★',
                        4 => '4 ★',
                        3 => '3 ★',
                        2 => '2 ★',
                        1 => '1 ★',
                    ]),
                SelectFilter::make('reviewable_type')
                    ->label('Typ')
                    ->options([
                        'course' => 'Kurz',
                        'lesson' => 'Lekce',
                        'service' => 'Služba',
                    ]),
            ])
            ->recordActions([
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
