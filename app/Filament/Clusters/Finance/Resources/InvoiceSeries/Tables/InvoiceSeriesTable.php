<?php

namespace App\Filament\Clusters\Finance\Resources\InvoiceSeries\Tables;

use App\Models\InvoiceSeries;
use App\Support\Invoices\DocumentNumberAllocator;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoiceSeriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable(),
                TextColumn::make('prefix')
                    ->label('Prefix'),
                TextColumn::make('document_type')
                    ->label('Typ')
                    ->badge(),
                TextColumn::make('format')
                    ->label('Formát'),
                TextColumn::make('current_number')
                    ->label('Aktuální pořadí'),
                TextColumn::make('next_number')
                    ->label('Další číslo')
                    ->state(fn (InvoiceSeries $record): string => app(DocumentNumberAllocator::class)->preview($record)),
                IconColumn::make('is_default')
                    ->label('Výchozí')
                    ->boolean(),
                IconColumn::make('reset_yearly')
                    ->label('Roční reset')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (InvoiceSeries $record): bool => ! $record->invoices()->exists()
                        && ! $record->cashReceipts()->exists()),
            ]);
    }
}
