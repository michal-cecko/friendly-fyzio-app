<?php

namespace App\Filament\Clusters\Finance\Resources\CashReceipts\Tables;

use App\Filament\Clusters\Finance\Resources\CashReceipts\Actions\DownloadReceiptPdfAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Číslo')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('client_name')
                    ->label('Přijato od')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Částka')
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' Kč')
                    ->sortable(),
                TextColumn::make('received_at')
                    ->label('Datum přijetí')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('invoice.invoice_number')
                    ->label('Faktura')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Vytvořeno')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ViewAction::make(),
                    DownloadReceiptPdfAction::make(),
                    DeleteAction::make(),
                ])
                    ->label('Akce')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->link()
                    ->color('gray'),
            ]);
    }
}
