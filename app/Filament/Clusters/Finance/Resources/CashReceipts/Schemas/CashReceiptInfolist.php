<?php

namespace App\Filament\Clusters\Finance\Resources\CashReceipts\Schemas;

use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Filament\Clusters\Finance\Resources\Payments\PaymentResource;
use App\Models\CashReceipt;
use App\Support\Invoices\CzechAmountInWords;
use App\Support\Pdf\InvoicePdfData;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CashReceiptInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pokladní doklad')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columnSpanFull()
                    ->columns(['default' => 2, 'lg' => 4])
                    ->schema([
                        TextEntry::make('receipt_number')
                            ->label('Číslo dokladu'),
                        TextEntry::make('client_name')
                            ->label('Přijato od'),
                        TextEntry::make('amount')
                            ->label('Částka')
                            ->formatStateUsing(fn (int $state): string => InvoicePdfData::money($state)
                                .' (slovy: '.CzechAmountInWords::for($state).')'),
                        TextEntry::make('received_at')
                            ->label('Datum přijetí')
                            ->date('d.m.Y'),
                        TextEntry::make('purpose')
                            ->label('Účel platby')
                            ->placeholder('—')
                            ->columnSpan(2),
                        TextEntry::make('received_by')
                            ->label('Přijal')
                            ->placeholder('—'),
                    ]),
                Section::make('Návaznosti')
                    ->icon(Heroicon::OutlinedLink)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextEntry::make('invoice.invoice_number')
                            ->label('Faktura')
                            ->url(fn (CashReceipt $record): ?string => $record->invoice !== null
                                ? InvoiceResource::getUrl('view', ['record' => $record->invoice])
                                : null)
                            ->placeholder('—'),
                        TextEntry::make('payment.number')
                            ->label('Platba')
                            ->formatStateUsing(fn ($state): string => 'č. '.$state)
                            ->url(fn (CashReceipt $record): ?string => $record->payment !== null
                                ? PaymentResource::getUrl('view', ['record' => $record->payment])
                                : null)
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
