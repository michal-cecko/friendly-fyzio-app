<?php

namespace App\Filament\Clusters\Obsah\Resources\Banners;

use App\Filament\Clusters\Obsah\ObsahCluster;
use App\Filament\Clusters\Obsah\Resources\Banners\Pages\CreateBanner;
use App\Filament\Clusters\Obsah\Resources\Banners\Pages\EditBanner;
use App\Filament\Clusters\Obsah\Resources\Banners\Pages\ListBanners;
use App\Filament\Clusters\Obsah\Resources\Banners\Schemas\BannerForm;
use App\Filament\Clusters\Obsah\Resources\Banners\Tables\BannersTable;
use App\Models\Banner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $cluster = ObsahCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'banner';
    }

    public static function getPluralModelLabel(): string
    {
        return 'bannery';
    }

    public static function getNavigationLabel(): string
    {
        return 'Bannery';
    }

    public static function form(Schema $schema): Schema
    {
        return BannerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BannersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
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
