<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\Workshops;

use App\Filament\Clusters\LekceWorkshopy\LekceWorkshopyCluster;
use App\Filament\Clusters\LekceWorkshopy\Resources\Workshops\Pages\CreateWorkshop;
use App\Filament\Clusters\LekceWorkshopy\Resources\Workshops\Pages\EditWorkshop;
use App\Filament\Clusters\LekceWorkshopy\Resources\Workshops\Pages\ListWorkshops;
use App\Filament\Clusters\LekceWorkshopy\Resources\Workshops\Pages\ViewWorkshop;
use App\Filament\Clusters\LekceWorkshopy\Resources\Workshops\Schemas\WorkshopForm;
use App\Filament\Clusters\LekceWorkshopy\Resources\Workshops\Schemas\WorkshopInfolist;
use App\Filament\Clusters\LekceWorkshopy\Resources\Workshops\Tables\WorkshopsTable;
use App\Models\Workshop;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WorkshopResource extends Resource
{
    protected static ?string $model = Workshop::class;

    protected static ?string $cluster = LekceWorkshopyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'workshop';
    }

    public static function getPluralModelLabel(): string
    {
        return 'workshopy';
    }

    public static function getNavigationLabel(): string
    {
        return 'Workshopy';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }

    public static function form(Schema $schema): Schema
    {
        return WorkshopForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkshopInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkshopsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['instructor', 'room']);
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
            'index' => ListWorkshops::route('/'),
            'create' => CreateWorkshop::route('/create'),
            'view' => ViewWorkshop::route('/{record}'),
            'edit' => EditWorkshop::route('/{record}/edit'),
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
