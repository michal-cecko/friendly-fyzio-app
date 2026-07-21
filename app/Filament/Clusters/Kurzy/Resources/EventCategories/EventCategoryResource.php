<?php

namespace App\Filament\Clusters\Kurzy\Resources\EventCategories;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages\CreateEventCategory;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages\EditEventCategory;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages\ListEventCategories;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages\ViewEventCategory;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Schemas\EventCategoryForm;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Schemas\EventCategoryInfolist;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Tables\EventCategoriesTable;
use App\Models\EventCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EventCategoryResource extends Resource
{
    protected static ?string $model = EventCategory::class;

    protected static ?string $cluster = KurzyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'kategorie akcí';
    }

    public static function getPluralModelLabel(): string
    {
        return 'kategorie akcí';
    }

    public static function getNavigationLabel(): string
    {
        return 'Kategorie akcí';
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
        return EventCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EventCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventCategoriesTable::configure($table);
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
            'index' => ListEventCategories::route('/'),
            'create' => CreateEventCategory::route('/create'),
            'view' => ViewEventCategory::route('/{record}'),
            'edit' => EditEventCategory::route('/{record}/edit'),
        ];
    }
}
