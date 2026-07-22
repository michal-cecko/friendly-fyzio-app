<?php

namespace App\Filament\Clusters\Provoz\Resources\ReservationDayWaitlist;

use App\Filament\Clusters\Provoz\ProvozCluster;
use App\Filament\Clusters\Provoz\Resources\ReservationDayWaitlist\Pages\ListReservationDayWaitlistEntries;
use App\Filament\Clusters\Provoz\Resources\ReservationDayWaitlist\Tables\ReservationDayWaitlistTable;
use App\Models\ReservationDayWaitlistEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReservationDayWaitlistResource extends Resource
{
    protected static ?string $model = ReservationDayWaitlistEntry::class;

    protected static ?string $cluster = ProvozCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin();
    }

    public static function getModelLabel(): string
    {
        return 'záznam pořadníku';
    }

    public static function getPluralModelLabel(): string
    {
        return 'pořadník na dny';
    }

    public static function getNavigationLabel(): string
    {
        return 'Pořadník na dny';
    }

    public static function table(Table $table): Table
    {
        return ReservationDayWaitlistTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservationDayWaitlistEntries::route('/'),
        ];
    }
}
