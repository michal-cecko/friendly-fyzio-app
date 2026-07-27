<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonBookings;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages\CreateLessonBooking;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages\EditLessonBooking;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages\ListLessonBookings;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages\ViewLessonBooking;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Schemas\LessonBookingForm;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Schemas\LessonBookingInfolist;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Tables\LessonBookingsTable;
use App\Filament\Support\RelationManagers\PaymentsRelationManager;
use App\Models\LessonBooking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LessonBookingResource extends Resource
{
    protected static ?string $model = LessonBooking::class;

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
     * Record titles are the object of modal headings ("Smazat přihlášku Jana Nováka"),
     * so they are written in the accusative.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        /** @var ?LessonBooking $record */
        return trim('přihlášku '.($record?->client?->name ?? ''));
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['client.name', 'client.email', 'lesson.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var LessonBooking $record */
        return trim(($record->client?->name ?? 'Neznámý klient').' — '.($record->lesson?->displayName() ?? 'Neznámá akce'));
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var LessonBooking $record */
        return array_filter([
            'Termín' => $record->lesson?->lesson_date?->format('j. n. Y'),
            'Platba' => $record->payment_status?->getLabel(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return LessonBookingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LessonBookingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LessonBookingsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'lesson.category']);
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
            'index' => ListLessonBookings::route('/'),
            'create' => CreateLessonBooking::route('/create'),
            'view' => ViewLessonBooking::route('/{record}'),
            'edit' => EditLessonBooking::route('/{record}/edit'),
        ];
    }
}
