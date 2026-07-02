<?php

namespace App\Filament\Clusters\System\Resources\TherapistProfiles;

use App\Filament\Clusters\System\Resources\TherapistProfiles\Pages\CreateTherapistProfile;
use App\Filament\Clusters\System\Resources\TherapistProfiles\Pages\EditTherapistProfile;
use App\Filament\Clusters\System\Resources\TherapistProfiles\Pages\ListTherapistProfiles;
use App\Filament\Clusters\System\Resources\TherapistProfiles\Schemas\TherapistProfileForm;
use App\Filament\Clusters\System\Resources\TherapistProfiles\Tables\TherapistProfilesTable;
use App\Filament\Clusters\System\SystemCluster;
use App\Models\TherapistProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TherapistProfileResource extends Resource
{
    protected static ?string $model = TherapistProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $cluster = SystemCluster::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function getModelLabel(): string
    {
        return 'terapeut';
    }

    public static function getPluralModelLabel(): string
    {
        return 'terapeuti';
    }

    public static function getNavigationLabel(): string
    {
        return 'Terapeuti';
    }

    public static function form(Schema $schema): Schema
    {
        return TherapistProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TherapistProfilesTable::configure($table);
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
            'index' => ListTherapistProfiles::route('/'),
            'create' => CreateTherapistProfile::route('/create'),
            'edit' => EditTherapistProfile::route('/{record}/edit'),
        ];
    }
}
