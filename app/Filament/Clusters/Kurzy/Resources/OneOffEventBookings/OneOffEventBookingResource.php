<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages\CreateOneOffEventBooking;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages\EditOneOffEventBooking;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages\ListOneOffEventBookings;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages\ViewOneOffEventBooking;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Schemas\OneOffEventBookingForm;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Schemas\OneOffEventBookingInfolist;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Tables\OneOffEventBookingsTable;
use App\Filament\Support\RelationManagers\PaymentsRelationManager;
use App\Models\OneOffEventBooking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OneOffEventBookingResource extends Resource
{
    protected static ?string $model = OneOffEventBooking::class;

    protected static ?string $cluster = KurzyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    protected static int $globalSearchResultsLimit = 10;

    public static function getModelLabel(): string
    {
        return 'přihláška na akci';
    }

    public static function getPluralModelLabel(): string
    {
        return 'přihlášky na akce';
    }

    public static function getNavigationLabel(): string
    {
        return 'Přihlášky na akce';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['client.name', 'client.email', 'event.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var OneOffEventBooking $record */
        return trim(($record->client?->name ?? 'Neznámý klient').' — '.($record->event?->name ?? 'Neznámá akce'));
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var OneOffEventBooking $record */
        return array_filter([
            'Termín' => $record->event?->event_date?->format('j. n. Y'),
            'Platba' => $record->payment_status?->getLabel(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return OneOffEventBookingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OneOffEventBookingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OneOffEventBookingsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'event.category']);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOneOffEventBookings::route('/'),
            'create' => CreateOneOffEventBooking::route('/create'),
            'view' => ViewOneOffEventBooking::route('/{record}'),
            'edit' => EditOneOffEventBooking::route('/{record}/edit'),
        ];
    }
}
