<?php

namespace App\Filament\Clusters\Finance\Resources\InvoiceSeries;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Clusters\Finance\Resources\InvoiceSeries\Pages\CreateInvoiceSeries;
use App\Filament\Clusters\Finance\Resources\InvoiceSeries\Pages\EditInvoiceSeries;
use App\Filament\Clusters\Finance\Resources\InvoiceSeries\Pages\ListInvoiceSeries;
use App\Filament\Clusters\Finance\Resources\InvoiceSeries\Schemas\InvoiceSeriesForm;
use App\Filament\Clusters\Finance\Resources\InvoiceSeries\Tables\InvoiceSeriesTable;
use App\Models\InvoiceSeries;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class InvoiceSeriesResource extends Resource
{
    protected static ?string $model = InvoiceSeries::class;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static ?int $navigationSort = 9;

    public static function getModelLabel(): string
    {
        return 'číselná řada';
    }

    public static function getPluralModelLabel(): string
    {
        return 'číselné řady';
    }

    public static function getNavigationLabel(): string
    {
        return 'Číselné řady';
    }

    /**
     * Record titles are the object of modal headings ("Smazat číselnou řadu Faktury"),
     * so they are written in the accusative.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        /** @var ?InvoiceSeries $record */
        return trim('číselnou řadu '.$record?->name);
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceSeriesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoiceSeriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoiceSeries::route('/'),
            'create' => CreateInvoiceSeries::route('/create'),
            'edit' => EditInvoiceSeries::route('/{record}/edit'),
        ];
    }
}
