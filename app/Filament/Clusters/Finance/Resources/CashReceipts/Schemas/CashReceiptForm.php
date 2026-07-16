<?php

namespace App\Filament\Clusters\Finance\Resources\CashReceipts\Schemas;

use App\Models\CashReceipt;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CashReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pokladní doklad')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 3])
                    ->schema([
                        Placeholder::make('receipt_number_display')
                            ->label('Číslo dokladu')
                            ->content(fn (?CashReceipt $record): string => $record?->receipt_number ?? '—'),
                        TextInput::make('client_name')
                            ->label('Přijato od')
                            ->required(),
                        TextInput::make('amount')
                            ->label('Částka')
                            ->integer()
                            ->required()
                            ->minValue(1)
                            ->suffix('Kč'),
                        TextInput::make('purpose')
                            ->label('Účel platby')
                            ->columnSpan(['default' => 1, 'lg' => 2]),
                        DatePicker::make('received_at')
                            ->label('Datum přijetí')
                            ->native(false)
                            ->required(),
                        TextInput::make('received_by')
                            ->label('Přijal'),
                        Placeholder::make('invoice_display')
                            ->label('Faktura')
                            ->content(fn (?CashReceipt $record): string => $record?->invoice?->invoice_number ?? '—'),
                        Placeholder::make('payment_display')
                            ->label('Platba')
                            ->content(fn (?CashReceipt $record): string => $record?->payment !== null
                                ? 'č. '.$record->payment->number
                                : '—'),
                    ]),
            ]);
    }
}
