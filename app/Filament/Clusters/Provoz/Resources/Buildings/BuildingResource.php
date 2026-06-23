<?php

namespace App\Filament\Clusters\Provoz\Resources\Buildings;

use App\Filament\Clusters\Provoz\ProvozCluster;
use App\Filament\Clusters\Provoz\Resources\Buildings\Pages\CreateBuilding;
use App\Filament\Clusters\Provoz\Resources\Buildings\Pages\EditBuilding;
use App\Filament\Clusters\Provoz\Resources\Buildings\Pages\ListBuildings;
use App\Filament\Clusters\Provoz\Resources\Buildings\RelationManagers\RoomsRelationManager;
use App\Filament\Clusters\Provoz\Resources\Buildings\Schemas\BuildingForm;
use App\Filament\Clusters\Provoz\Resources\Buildings\Tables\BuildingsTable;
use App\Models\Building;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BuildingResource extends Resource
{
    protected static ?string $model = Building::class;

    protected static ?string $cluster = ProvozCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    // Buildings rarely change and are created inline from the Room form, so they
    // are hidden from the sidebar. The pages stay routable via direct URL.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'budova';
    }

    public static function getPluralModelLabel(): string
    {
        return 'budovy';
    }

    public static function getNavigationLabel(): string
    {
        return 'Budovy';
    }

    public static function form(Schema $schema): Schema
    {
        return BuildingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BuildingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RoomsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuildings::route('/'),
            'create' => CreateBuilding::route('/create'),
            'edit' => EditBuilding::route('/{record}/edit'),
        ];
    }
}
