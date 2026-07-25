<?php

namespace App\Filament\Clusters\Finance\Resources\Payments\Schemas;

use App\Contracts\Payable;
use App\Filament\Clusters\Finance\Resources\CashReceipts\CashReceiptResource;
use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Support\PayableLinks;
use App\Models\Payment;
use App\Support\Invoices\PayableTitle;
use App\Support\Pdf\InvoicePdfData;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Platba')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columnSpanFull()
                    ->columns(['default' => 2, 'lg' => 4])
                    ->schema([
                        TextEntry::make('number')
                            ->label('Číslo platby')
                            ->formatStateUsing(fn ($state): string => 'č. '.$state),
                        TextEntry::make('variable_symbol')
                            ->label('Variabilní symbol'),
                        TextEntry::make('amount')
                            ->label('Částka')
                            ->formatStateUsing(fn (int $state): string => InvoicePdfData::money($state)),
                        TextEntry::make('method')
                            ->label('Způsob')
                            ->badge(),
                        TextEntry::make('status')
                            ->label('Stav')
                            ->badge(),
                        TextEntry::make('client.name')
                            ->label('Klient')
                            ->placeholder('—')
                            ->url(fn (Payment $record): ?string => $record->client !== null
                                ? ClientResource::getUrl('view', ['record' => $record->client])
                                : null),
                        TextEntry::make('due_at')
                            ->label('Splatnost')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('paid_at')
                            ->label('Zaplaceno')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ]),
                Section::make('Návaznosti')
                    ->icon(Heroicon::OutlinedLink)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 3])
                    ->schema([
                        TextEntry::make('payable_link')
                            ->label('Za co')
                            ->state(fn (Payment $record): string => $record->payable instanceof Payable
                                ? PayableTitle::render($record->payable)['title']
                                : '—')
                            ->url(fn (Payment $record): ?string => PayableLinks::url($record->payable)),
                        TextEntry::make('invoice.invoice_number')
                            ->label('Faktura')
                            ->url(fn (Payment $record): ?string => $record->invoice !== null
                                ? InvoiceResource::getUrl('view', ['record' => $record->invoice])
                                : null)
                            ->placeholder('—'),
                        TextEntry::make('cashReceipt.receipt_number')
                            ->label('Pokladní doklad')
                            ->url(fn (Payment $record): ?string => $record->cashReceipt !== null
                                ? CashReceiptResource::getUrl('view', ['record' => $record->cashReceipt])
                                : null)
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
