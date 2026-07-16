<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Schemas;

use App\Filament\Clusters\Finance\Resources\Payments\PaymentResource;
use App\Models\Invoice;
use App\Support\Pdf\InvoicePdfData;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Doklad')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columnSpanFull()
                    ->columns(['default' => 2, 'lg' => 4])
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('Číslo faktury'),
                        TextEntry::make('status')
                            ->label('Stav')
                            ->badge(),
                        TextEntry::make('payment_method')
                            ->label('Způsob platby')
                            ->badge(),
                        TextEntry::make('amount')
                            ->label('Částka')
                            ->formatStateUsing(fn (int $state): string => InvoicePdfData::money($state)),
                        TextEntry::make('issued_at')
                            ->label('Vystaveno')
                            ->date('d.m.Y'),
                        TextEntry::make('due_at')
                            ->label('Splatnost')
                            ->date('d.m.Y'),
                        TextEntry::make('paid_at')
                            ->label('Uhrazeno')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('variable_symbol')
                            ->label('Variabilní symbol')
                            ->placeholder('—'),
                    ]),
                Section::make('Odběratel')
                    ->icon(Heroicon::OutlinedUser)
                    ->columnSpanFull()
                    ->columns(['default' => 2, 'lg' => 4])
                    ->schema([
                        TextEntry::make('client_snapshot.name')
                            ->label('Jméno / název'),
                        TextEntry::make('client_snapshot.address')
                            ->label('Adresa')
                            ->placeholder('—'),
                        TextEntry::make('client_snapshot.ico')
                            ->label('IČO')
                            ->placeholder('—'),
                        TextEntry::make('client_snapshot.dic')
                            ->label('DIČ')
                            ->placeholder('—'),
                    ]),
                Section::make('Položky')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->columns(['default' => 2, 'lg' => 6])
                            ->schema([
                                TextEntry::make('title')
                                    ->label('Položka')
                                    ->columnSpan(['default' => 2, 'lg' => 3]),
                                TextEntry::make('quantity')
                                    ->label('Počet'),
                                TextEntry::make('unit_price')
                                    ->label('Cena/ks')
                                    ->formatStateUsing(fn (int $state): string => InvoicePdfData::money($state)),
                                TextEntry::make('total')
                                    ->label('Celkem')
                                    ->formatStateUsing(fn (int $state): string => InvoicePdfData::money($state)),
                            ]),
                    ]),
                Section::make('Návaznosti')
                    ->icon(Heroicon::OutlinedLink)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 3])
                    ->schema([
                        TextEntry::make('payments.number')
                            ->label('Platby')
                            ->formatStateUsing(fn ($state): string => 'Platba č. '.$state)
                            ->url(fn (Invoice $record): ?string => $record->payments()->exists()
                                ? PaymentResource::getUrl('view', ['record' => $record->payments()->latest()->first()])
                                : null)
                            ->placeholder('Žádné platby'),
                        TextEntry::make('cashReceipt.receipt_number')
                            ->label('Pokladní doklad')
                            ->placeholder('—'),
                        TextEntry::make('invoiceable_type')
                            ->label('Vystaveno k')
                            ->formatStateUsing(fn (?string $state): string => $state ?? '—')
                            ->placeholder('Samostatná faktura'),
                    ]),
            ]);
    }
}
