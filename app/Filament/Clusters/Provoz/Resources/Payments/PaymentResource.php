<?php

namespace App\Filament\Clusters\Provoz\Resources\Payments;

use App\Filament\Clusters\Provoz\ProvozCluster;
use App\Filament\Clusters\Provoz\Resources\Payments\Pages\ListPayments;
use App\Filament\Clusters\Provoz\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $cluster = ProvozCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 4;

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
     * Payments are raised by the system (e.g. a storno fee); they are never created by hand.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }
}
