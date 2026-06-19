<?php

namespace App\Filament\Resources\Navigations;

use App\Filament\Resources\Navigations\Pages\EditNavigation;
use App\Filament\Resources\Navigations\Pages\ListNavigations;
use App\Filament\Resources\Navigations\Schemas\NavigationForm;
use App\Filament\Resources\Navigations\Tables\NavigationsTable;
use App\Models\Navigation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NavigationResource extends Resource
{
    protected static ?string $model = Navigation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|UnitEnum|null $navigationGroup = 'Obsah';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'menu';
    }

    public static function getPluralModelLabel(): string
    {
        return 'menu';
    }

    public static function getNavigationLabel(): string
    {
        return 'Menu (navigace)';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return NavigationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NavigationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNavigations::route('/'),
            'edit' => EditNavigation::route('/{record}/edit'),
        ];
    }
}
