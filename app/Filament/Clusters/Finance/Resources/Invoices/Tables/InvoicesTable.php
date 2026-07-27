<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\DownloadInvoicePdfAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\DownloadInvoicesZipBulkAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\MarkInvoicePaidAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\SendInvoiceAction;
use App\Filament\Exports\InvoiceExporter;
use App\Models\Invoice;
use App\Support\Payments\PastDue;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Číslo')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('issued_at')
                    ->label('Vystaveno')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('client_name')
                    ->label('Odběratel')
                    ->state(fn (Invoice $record): string => $record->client_snapshot['name']
                        ?? $record->client?->name
                        ?? '—')
                    ->searchable(query: fn ($query, string $search) => $query->whereHas(
                        'client',
                        fn ($clients) => $clients->where('name', 'ilike', "%{$search}%"),
                    )),
                TextColumn::make('amount')
                    ->label('Částka')
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' Kč')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Způsob')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('due_at')
                    ->label('Splatnost')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Uhrazeno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('variable_symbol')
                    ->label('VS')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(InvoiceStatus::class),
                SelectFilter::make('payment_method')
                    ->label('Způsob platby')
                    ->options(PaymentMethod::class),
                SelectFilter::make('series_id')
                    ->label('Číselná řada')
                    ->relationship('series', 'name'),
                Filter::make('past_due')
                    ->label('Po splatnosti')
                    ->query(fn (Builder $query): Builder => PastDue::invoices($query))
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ViewAction::make(),
                    DownloadInvoicePdfAction::make(),
                    SendInvoiceAction::make(),
                    MarkInvoicePaidAction::make(),
                ])
                    ->label('Akce')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->link()
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(InvoiceExporter::class)
                        ->label('Export pro účetní')
                        ->modalHeading('Exportovat faktury'),
                    DownloadInvoicesZipBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
