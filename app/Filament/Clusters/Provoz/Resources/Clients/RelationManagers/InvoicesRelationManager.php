<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers;

use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only list of the client's invoices — rows deep-link into the Finance
 * cluster's invoice detail. Invoices are issued by the invoicing pipeline,
 * never edited from the client page.
 */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Faktury';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedDocumentText;

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Číslo')
                    ->weight('semibold'),
                TextColumn::make('issued_at')
                    ->label('Vystaveno')
                    ->date('d.m.Y'),
                TextColumn::make('amount')
                    ->label('Částka')
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' Kč'),
                TextColumn::make('payment_method')
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
                    ->label('Uhrazeno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('issued_at', 'desc')
            ->emptyStateHeading('Žádné faktury')
            ->recordUrl(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]))
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
