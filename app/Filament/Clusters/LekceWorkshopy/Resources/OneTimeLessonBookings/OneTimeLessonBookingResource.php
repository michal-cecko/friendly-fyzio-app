<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings;

use App\Filament\Clusters\LekceWorkshopy\LekceWorkshopyCluster;
use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Pages\CreateOneTimeLessonBooking;
use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Pages\EditOneTimeLessonBooking;
use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Pages\ListOneTimeLessonBookings;
use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Pages\ViewOneTimeLessonBooking;
use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Schemas\OneTimeLessonBookingForm;
use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Schemas\OneTimeLessonBookingInfolist;
use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Tables\OneTimeLessonBookingsTable;
use App\Models\OneTimeLessonBooking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OneTimeLessonBookingResource extends Resource
{
    protected static ?string $model = OneTimeLessonBooking::class;

    protected static ?string $cluster = LekceWorkshopyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

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
            //
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
