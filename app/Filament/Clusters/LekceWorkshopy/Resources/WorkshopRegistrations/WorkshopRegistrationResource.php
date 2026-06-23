<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations;

use App\Filament\Clusters\LekceWorkshopy\LekceWorkshopyCluster;
use App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\Pages\CreateWorkshopRegistration;
use App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\Pages\EditWorkshopRegistration;
use App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\Pages\ListWorkshopRegistrations;
use App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\Pages\ViewWorkshopRegistration;
use App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\Schemas\WorkshopRegistrationForm;
use App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\Schemas\WorkshopRegistrationInfolist;
use App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\Tables\WorkshopRegistrationsTable;
use App\Models\WorkshopRegistration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkshopRegistrationResource extends Resource
{
    protected static ?string $model = WorkshopRegistration::class;

    protected static ?string $cluster = LekceWorkshopyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return 'registrace';
    }

    public static function getPluralModelLabel(): string
    {
        return 'registrace';
    }

    public static function getNavigationLabel(): string
    {
        return 'Registrace';
    }

    public static function form(Schema $schema): Schema
    {
        return WorkshopRegistrationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkshopRegistrationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkshopRegistrationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'workshop']);
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
            'index' => ListWorkshopRegistrations::route('/'),
            'create' => CreateWorkshopRegistration::route('/create'),
            'view' => ViewWorkshopRegistration::route('/{record}'),
            'edit' => EditWorkshopRegistration::route('/{record}/edit'),
        ];
    }
}
