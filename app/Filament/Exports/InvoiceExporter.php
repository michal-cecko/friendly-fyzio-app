<?php

namespace App\Filament\Exports;

use App\Models\Invoice;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * The accountant export: one row per invoice with the columns the účetní needs.
 */
class InvoiceExporter extends Exporter
{
    protected static ?string $model = Invoice::class;

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('invoice_number')
                ->label('Číslo'),
            ExportColumn::make('issued_at')
                ->label('Vystaveno')
                ->formatStateUsing(fn ($state): string => $state?->format('d.m.Y') ?? ''),
            ExportColumn::make('due_at')
                ->label('Splatnost')
                ->formatStateUsing(fn ($state): string => $state?->format('d.m.Y') ?? ''),
            ExportColumn::make('client_snapshot.name')
                ->label('Odběratel'),
            ExportColumn::make('client_snapshot.ico')
                ->label('IČO odběratele'),
            ExportColumn::make('amount')
                ->label('Částka Kč'),
            ExportColumn::make('status')
                ->label('Stav')
                ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? ''),
            ExportColumn::make('payment_method')
                ->label('Způsob platby')
                ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? ''),
            ExportColumn::make('variable_symbol')
                ->label('VS'),
            ExportColumn::make('paid_at')
                ->label('Uhrazeno')
                ->formatStateUsing(fn ($state): string => $state?->format('d.m.Y') ?? ''),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export faktur je hotový: '.number_format($export->successful_rows).' řádků.';

        if ($failed = $export->getFailedRowsCount()) {
            $body .= ' Nepodařilo se exportovat '.number_format($failed).' řádků.';
        }

        return $body;
    }
}
