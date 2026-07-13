<?php

namespace App\Filament\Clusters\Provoz\Resources\Services;

use App\Filament\Clusters\Provoz\ProvozCluster;
use App\Filament\Clusters\Provoz\Resources\Services\Pages\CreateService;
use App\Filament\Clusters\Provoz\Resources\Services\Pages\EditService;
use App\Filament\Clusters\Provoz\Resources\Services\Pages\ListServices;
use App\Filament\Clusters\Provoz\Resources\Services\Pages\ViewService;
use App\Filament\Clusters\Provoz\Resources\Services\Schemas\ServiceForm;
use App\Filament\Clusters\Provoz\Resources\Services\Schemas\ServiceInfolist;
use App\Filament\Clusters\Provoz\Resources\Services\Tables\ServicesTable;
use App\Models\Service;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $cluster = ProvozCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'služba';
    }

    public static function getPluralModelLabel(): string
    {
        return 'služby';
    }

    public static function getNavigationLabel(): string
    {
        return 'Služby';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Service $record */
        return array_filter([
            'Kategorie' => $record->category?->name,
            'Délka' => $record->duration_minutes ? $record->duration_minutes.' min' : null,
            'Cena' => $record->price ? number_format($record->price, 0, ',', ' ').' Kč' : null,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return ServiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ServiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServicesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('category');
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
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'view' => ViewService::route('/{record}'),
            'edit' => EditService::route('/{record}/edit'),
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
