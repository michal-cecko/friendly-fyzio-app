<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations;

use App\Filament\Clusters\Provoz\ProvozCluster;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\CreateReservation;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\EditReservation;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Clusters\Provoz\Resources\Reservations\Tables\ReservationsTable;
use App\Models\Reservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $cluster = ProvozCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return 'rezervace';
    }

    public static function getPluralModelLabel(): string
    {
        return 'rezervace';
    }

    public static function getNavigationLabel(): string
    {
        return 'Rezervace';
    }

    public static function form(Schema $schema): Schema
    {
        return ReservationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReservationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReservationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['client', 'service', 'therapist.user', 'room']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservations::route('/'),
            'create' => CreateReservation::route('/create'),
            'view' => ViewReservation::route('/{record}'),
            'edit' => EditReservation::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
