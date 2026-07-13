<?php

namespace App\Filament\Clusters\Provoz\Resources\Rooms;

use App\Filament\Clusters\Provoz\ProvozCluster;
use App\Filament\Clusters\Provoz\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Clusters\Provoz\Resources\Rooms\Pages\EditRoom;
use App\Filament\Clusters\Provoz\Resources\Rooms\Pages\ListRooms;
use App\Filament\Clusters\Provoz\Resources\Rooms\Pages\ViewRoom;
use App\Filament\Clusters\Provoz\Resources\Rooms\Schemas\RoomForm;
use App\Filament\Clusters\Provoz\Resources\Rooms\Schemas\RoomInfolist;
use App\Filament\Clusters\Provoz\Resources\Rooms\Tables\RoomsTable;
use App\Models\Room;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $cluster = ProvozCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'místnost';
    }

    public static function getPluralModelLabel(): string
    {
        return 'místnosti';
    }

    public static function getNavigationLabel(): string
    {
        return 'Místnosti';
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Room $record */
        return array_filter([
            'Budova' => $record->building?->name,
        ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['building']);
    }

    public static function form(Schema $schema): Schema
    {
        return RoomForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RoomInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRooms::route('/'),
            'create' => CreateRoom::route('/create'),
            'view' => ViewRoom::route('/{record}'),
            'edit' => EditRoom::route('/{record}/edit'),
        ];
    }
}
