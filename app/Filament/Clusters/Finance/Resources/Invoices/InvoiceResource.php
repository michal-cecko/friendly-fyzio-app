<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Clusters\Finance\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Clusters\Finance\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Clusters\Finance\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Clusters\Finance\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Clusters\Finance\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Clusters\Finance\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\Clusters\Finance\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 1;

    protected static int $globalSearchResultsLimit = 10;

    public static function getModelLabel(): string
    {
        return 'faktura';
    }

    public static function getPluralModelLabel(): string
    {
        return 'faktury';
    }

    public static function getNavigationLabel(): string
    {
        return 'Faktury';
    }

    /**
     * Record titles are the object of modal headings ("Smazat fakturu 2026-0001"),
     * so they are written in the accusative.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        /** @var ?Invoice $record */
        return trim('fakturu '.$record?->invoice_number);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'variable_symbol', 'client.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var Invoice $record */
        return $record->invoice_number.' — '.($record->client_snapshot['name'] ?? $record->client?->name ?? '');
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Invoice $record */
        return array_filter([
            'Částka' => number_format($record->amount, 0, ',', ' ').' Kč',
            'Stav' => $record->status?->getLabel(),
            'Vystaveno' => $record->issued_at?->format('j. n. Y'),
        ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['client']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'series']);
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
