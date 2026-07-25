<?php

namespace App\Filament\Clusters\Finance\Resources\Payments;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Clusters\Finance\Resources\Payments\Pages\ListPayments;
use App\Filament\Clusters\Finance\Resources\Payments\Pages\ViewPayment;
use App\Filament\Clusters\Finance\Resources\Payments\Schemas\PaymentInfolist;
use App\Filament\Clusters\Finance\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 4;

    protected static int $globalSearchResultsLimit = 10;

    public static function getModelLabel(): string
    {
        return 'platba';
    }

    public static function getPluralModelLabel(): string
    {
        return 'platby';
    }

    public static function getNavigationLabel(): string
    {
        return 'Platby';
    }

    /**
     * Record titles are the object of modal headings ("Smazat platbu č. 12"),
     * so they are written in the accusative.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        /** @var ?Payment $record */
        return trim('platbu č. '.$record?->number);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'variable_symbol', 'client.name', 'client.email'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var Payment $record */
        return trim('Platba č. '.$record->number.' — '.($record->client?->name ?? 'Neznámý klient'));
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Payment $record */
        return array_filter([
            'Částka' => number_format($record->amount, 0, ',', ' ').' Kč',
            'Stav' => $record->status?->getLabel(),
            'Zaplaceno' => $record->paid_at?->format('j. n. Y'),
        ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['client']);
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        /** @var Payment $record */
        return static::getUrl('view', ['record' => $record]);
    }

    /**
     * Payments are raised by the system (a storno fee, "Zaznamenat platbu" on a
     * payable, …); they are never created by hand from this resource.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
