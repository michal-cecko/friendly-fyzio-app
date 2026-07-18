<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers;

use App\Models\CreditTransaction;
use App\Support\Credits\CreditLedger;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only ledger view: credit is only ever written through
 * {@see CreditLedger} (the "Upravit kredit" action on the
 * client, or the credits:expire command), never edited row by row.
 */
class CreditTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'creditTransactions';

    protected static ?string $title = 'Kredit';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedBanknotes;

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('type')
                    ->label('Typ')
                    ->badge(),
                TextColumn::make('description')
                    ->label('Popis')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('expires_at')
                    ->label('Platnost')
                    ->date('d.m.Y')
                    ->placeholder('—'),
                TextColumn::make('amount')
                    ->label('Částka')
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').number_format($state, 0, ',', ' ').' Kč')
                    ->color(fn (CreditTransaction $record): string => $record->amount > 0 ? 'success' : 'danger')
                    ->weight('semibold'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Žádné pohyby kreditu')
            ->emptyStateDescription('Kredit klientovi připíšete akcí „Upravit kredit" v hlavičce.')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
