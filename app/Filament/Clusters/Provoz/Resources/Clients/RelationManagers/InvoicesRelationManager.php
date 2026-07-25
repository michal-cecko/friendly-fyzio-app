<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers;

use App\Filament\Clusters\Finance\Resources\CashReceipts\Actions\GenerateCashReceiptAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\DownloadInvoicePdfAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\MarkInvoicePaidAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\SendInvoiceAction;
use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The client's invoices — rows deep-link into the Finance cluster's invoice
 * detail, and the row action group carries the same servicing actions as the
 * Finance table (PDF, e-mail, mark paid, cash receipt). Invoices are issued by
 * the invoicing pipeline and edited in Finance, never from the client page.
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
                    ->searchable()
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
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->url(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record])),
                    DownloadInvoicePdfAction::make(),
                    SendInvoiceAction::make(),
                    MarkInvoicePaidAction::make(),
                    GenerateCashReceiptAction::make(),
                ])
                    ->label('Akce')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->link()
                    ->color('gray'),
            ])
            ->toolbarActions([]);
    }
}
