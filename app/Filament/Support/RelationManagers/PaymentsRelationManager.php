<?php

namespace App\Filament\Support\RelationManagers;

use App\Filament\Clusters\Finance\Resources\Payments\PaymentResource;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Payments attached to any payable (reservation, course enrollment, workshop
 * registration, one-time lesson booking) — rows deep-link into the Finance
 * cluster's payment detail.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Platby';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedBanknotes;

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->columns([
                TextColumn::make('number')
                    ->label('Č. platby')
                    ->searchable()
                    ->formatStateUsing(fn ($state): string => 'č. '.$state),
                TextColumn::make('variable_symbol')
                    ->label('VS')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Částka')
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' Kč'),
                TextColumn::make('method')
                    ->label('Způsob')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('due_at')
                    ->label('Splatnost')
                    ->date('d.m.Y')
                    ->placeholder('—'),
                TextColumn::make('paid_at')
                    ->label('Zaplaceno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
                TextColumn::make('invoice.invoice_number')
                    ->label('Faktura')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Payment $record): string => PaymentResource::getUrl('view', ['record' => $record]))
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
