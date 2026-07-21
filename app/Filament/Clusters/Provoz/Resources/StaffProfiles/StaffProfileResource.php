<?php

namespace App\Filament\Clusters\Provoz\Resources\StaffProfiles;

use App\Filament\Clusters\Provoz\ProvozCluster;
use App\Filament\Clusters\Provoz\Resources\StaffProfiles\Pages\CreateStaffProfile;
use App\Filament\Clusters\Provoz\Resources\StaffProfiles\Pages\EditStaffProfile;
use App\Filament\Clusters\Provoz\Resources\StaffProfiles\Pages\ListStaffProfiles;
use App\Filament\Clusters\Provoz\Resources\StaffProfiles\Schemas\StaffProfileForm;
use App\Filament\Clusters\Provoz\Resources\StaffProfiles\Tables\StaffProfilesTable;
use App\Models\StaffProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffProfileResource extends Resource
{
    protected static ?string $model = StaffProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $cluster = ProvozCluster::class;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function getModelLabel(): string
    {
        return 'člen týmu';
    }

    public static function getPluralModelLabel(): string
    {
        return 'členové týmu';
    }

    public static function getNavigationLabel(): string
    {
        return 'Tým';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['slug', 'user.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var StaffProfile $record */
        return $record->user?->name ?? $record->slug;
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var StaffProfile $record */
        return array_filter([
            'Titul' => $record->title,
            'Stav' => $record->published_at ? 'Publikováno' : 'Koncept',
        ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['user']);
    }

    public static function form(Schema $schema): Schema
    {
        return StaffProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffProfilesTable::configure($table);
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
            'index' => ListStaffProfiles::route('/'),
            'create' => CreateStaffProfile::route('/create'),
            'edit' => EditStaffProfile::route('/{record}/edit'),
        ];
    }
}
