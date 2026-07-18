<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers;

use App\Models\SubstituteToken;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only view of the client's substitute-lesson tokens ("náhradní vstupy"):
 * where the token came from, its validity, current state, and where it was
 * redeemed. Tokens are minted/redeemed through the client zone, never here.
 */
class SubstituteTokensRelationManager extends RelationManager
{
    protected static string $relationship = 'substituteTokens';

    protected static ?string $title = 'Náhradní vstupy';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedTicket;

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'sourceLesson.series.course',
                'usedForLesson.series.course',
            ]))
            ->columns([
                TextColumn::make('sourceLesson.series.course.name')
                    ->label('Kurz')
                    ->description(fn (SubstituteToken $record): ?string => $record->sourceLesson?->lesson_date?->format('d.m.Y'))
                    ->placeholder('—'),
                TextColumn::make('expires_at')
                    ->label('Platnost')
                    ->date('d.m.Y')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->state(fn (SubstituteToken $record): string => match (true) {
                        $record->used_at !== null => 'Použitý',
                        $record->expires_at !== null && $record->expires_at->isPast() => 'Propadlý',
                        default => 'Dostupný',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Použitý' => 'gray',
                        'Propadlý' => 'danger',
                        default => 'success',
                    }),
                TextColumn::make('usedForLesson.series.course.name')
                    ->label('Náhrada')
                    ->description(fn (SubstituteToken $record): ?string => $record->usedForLesson?->lesson_date?->format('d.m.Y'))
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Žádné náhradní vstupy')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
