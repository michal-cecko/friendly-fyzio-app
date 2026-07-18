<?php

namespace App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings;

use App\Filament\Clusters\Lekce\LekceCluster;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\Pages\CreateOneTimeLessonBooking;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\Pages\EditOneTimeLessonBooking;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\Pages\ListOneTimeLessonBookings;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\Pages\ViewOneTimeLessonBooking;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\Schemas\OneTimeLessonBookingForm;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\Schemas\OneTimeLessonBookingInfolist;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\Tables\OneTimeLessonBookingsTable;
use App\Filament\Support\RelationManagers\PaymentsRelationManager;
use App\Models\OneTimeLessonBooking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OneTimeLessonBookingResource extends Resource
{
    protected static ?string $model = OneTimeLessonBooking::class;

    protected static ?string $cluster = LekceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 2;

    protected static int $globalSearchResultsLimit = 10;

    public static function getModelLabel(): string
    {
        return 'rezervace lekce';
    }

    public static function getPluralModelLabel(): string
    {
        return 'rezervace lekcí';
    }

    public static function getNavigationLabel(): string
    {
        return 'Rezervace lekcí';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['client.name', 'client.email', 'lesson.course.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var OneTimeLessonBooking $record */
        return trim(($record->client?->name ?? 'Neznámý klient').' — '.($record->lesson?->course?->name ?? 'Neznámá lekce'));
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var OneTimeLessonBooking $record */
        return array_filter([
            'Datum lekce' => $record->lesson?->lesson_date?->format('j. n. Y'),
            'Platba' => $record->payment_status?->getLabel(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return OneTimeLessonBookingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OneTimeLessonBookingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OneTimeLessonBookingsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'lesson.course']);
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
            'index' => ListOneTimeLessonBookings::route('/'),
            'create' => CreateOneTimeLessonBooking::route('/create'),
            'view' => ViewOneTimeLessonBooking::route('/{record}'),
            'edit' => EditOneTimeLessonBooking::route('/{record}/edit'),
        ];
    }
}
