<?php

namespace App\Filament\Clusters\System\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Clusters\System\Resources\Users\Pages\CreateUser;
use App\Filament\Clusters\System\Resources\Users\Pages\EditUser;
use App\Filament\Clusters\System\Resources\Users\Pages\ListUsers;
use App\Filament\Clusters\System\Resources\Users\Pages\ViewUser;
use App\Filament\Clusters\System\Resources\Users\RelationManagers\InstructedLessonsRelationManager;
use App\Filament\Clusters\System\Resources\Users\RelationManagers\TherapistReservationsRelationManager;
use App\Filament\Clusters\System\Resources\Users\Schemas\UserForm;
use App\Filament\Clusters\System\Resources\Users\Schemas\UserInfolist;
use App\Filament\Clusters\System\Resources\Users\Tables\UsersTable;
use App\Filament\Clusters\System\SystemCluster;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $cluster = SystemCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'uživatel';
    }

    public static function getPluralModelLabel(): string
    {
        return 'uživatelé';
    }

    public static function getNavigationLabel(): string
    {
        return 'Uživatelé';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNot('role', UserRole::Customer);
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TherapistReservationsRelationManager::class,
            InstructedLessonsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
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
