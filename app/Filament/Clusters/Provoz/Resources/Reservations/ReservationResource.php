<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations;

use App\Filament\Clusters\Provoz\ProvozCluster;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\EditReservation;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Clusters\Provoz\Resources\Reservations\RelationManagers\NotesRelationManager;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Clusters\Provoz\Resources\Reservations\Tables\ReservationsTable;
use App\Filament\Support\Concerns\ScopedToTherapist;
use App\Filament\Support\RelationManagers\PaymentsRelationManager;
use App\Models\Reservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReservationResource extends Resource
{
    use ScopedToTherapist;

    protected static ?string $model = Reservation::class;

    protected static ?string $cluster = ProvozCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 0;

    protected static int $globalSearchResultsLimit = 10;

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

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['client.name', 'client.email', 'client.phone', 'service.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var Reservation $record */
        return trim(($record->client?->name ?? 'Neznámý klient').' — '.($record->service?->name ?? 'Neznámá služba'));
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Reservation $record */
        return array_filter([
            'Termín' => $record->startsAt()->format('j. n. Y H:i'),
            'Terapeut' => $record->therapist?->user?->name,
            'Stav' => $record->status?->getLabel(),
        ]);
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
            ->with(['client', 'service', 'therapist.user', 'room'])
            ->when(static::therapistProfileScopeId(), fn (Builder $query, string $id) => $query->where('therapist_id', $id));
    }

    public static function getRelations(): array
    {
        return [
            NotesRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservations::route('/'),
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
