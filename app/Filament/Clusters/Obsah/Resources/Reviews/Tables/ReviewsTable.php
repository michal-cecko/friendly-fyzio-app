<?php

namespace App\Filament\Clusters\Obsah\Resources\Reviews\Tables;

use App\Filament\Support\Tables\TimestampColumns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReviewsTable
{
    /**
     * Human labels for the polymorphic reviewable morph aliases.
     *
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'course' => 'Kurz',
        'course_series' => 'Kurz',
        'workshop' => 'Workshop',
        'service' => 'Služba',
        'one_time_lesson' => 'Lekce',
        'reservation' => 'Rezervace',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('rating')
                    ->label('Hodnocení')
                    ->formatStateUsing(fn (?int $state): string => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state))
                    ->sortable(),
                TextColumn::make('author_name')
                    ->label('Autor')
                    ->description(fn ($record): ?string => $record->author_role)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('content')
                    ->label('Text')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('reviewable_type')
                    ->label('Vztahuje se k')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => self::TYPE_LABELS[$state] ?? 'Obecná'),
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
                        'workshop' => 'Workshop',
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
