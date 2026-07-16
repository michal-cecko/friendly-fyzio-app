<?php

namespace App\Filament\Clusters\Finance\Resources\CashReceipts;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Clusters\Finance\Resources\CashReceipts\Pages\EditCashReceipt;
use App\Filament\Clusters\Finance\Resources\CashReceipts\Pages\ListCashReceipts;
use App\Filament\Clusters\Finance\Resources\CashReceipts\Pages\ViewCashReceipt;
use App\Filament\Clusters\Finance\Resources\CashReceipts\Schemas\CashReceiptForm;
use App\Filament\Clusters\Finance\Resources\CashReceipts\Schemas\CashReceiptInfolist;
use App\Filament\Clusters\Finance\Resources\CashReceipts\Tables\CashReceiptsTable;
use App\Models\CashReceipt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class CashReceiptResource extends Resource
{
    protected static ?string $model = CashReceipt::class;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 2;

    protected static int $globalSearchResultsLimit = 10;

    public static function getModelLabel(): string
    {
        return 'pokladní doklad';
    }

    public static function getPluralModelLabel(): string
    {
        return 'pokladní doklady';
    }

    public static function getNavigationLabel(): string
    {
        return 'Pokladní doklady';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['receipt_number', 'client_name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var CashReceipt $record */
        return $record->receipt_number.' — '.($record->client_name ?? '');
    }

    /**
     * Receipts arise from payments/invoices via the generate actions only.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CashReceiptForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CashReceiptInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashReceiptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashReceipts::route('/'),
            'view' => ViewCashReceipt::route('/{record}'),
            'edit' => EditCashReceipt::route('/{record}/edit'),
        ];
    }
}
