<?php

namespace App\Filament\Clusters\Provoz\Resources\Users;

use App\Filament\Clusters\Provoz\ProvozCluster;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\CreateUser;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\EditUser;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\ListUsers;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\ViewUser;
use App\Filament\Clusters\Provoz\Resources\Users\RelationManagers\InstructedLessonsRelationManager;
use App\Filament\Clusters\Provoz\Resources\Users\RelationManagers\StaffClientNotesRelationManager;
use App\Filament\Clusters\Provoz\Resources\Users\RelationManagers\TherapistReservationsRelationManager;
use App\Filament\Clusters\Provoz\Resources\Users\Schemas\UserForm;
use App\Filament\Clusters\Provoz\Resources\Users\Schemas\UserInfolist;
use App\Filament\Clusters\Provoz\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $cluster = ProvozCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Staff-account management is administrators only. The User model is shared
     * with the customer-facing ClientResource (which therapists may reach), so
     * this resource is gated by role rather than by the shared `…:User`
     * permission to keep therapists out of staff accounts.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * Creating a staff account is admin-level; but only a super-admin may create
     * one carrying an admin/super-admin capability — the capability checkboxes
     * for those tiers are already disabled for non-super-admins, and the delete
     * guard mirrors this.
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * An admin/super-admin account may only be deleted by a super-admin, so a
     * plain admin can't remove a peer or the owner. Everyone else stays deletable
     * by any admin.
     */
    public static function canDeleteUser(User $record): bool
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return false;
        }

        return $record->isAdmin() ? $actor->isSuperAdmin() : $actor->isAdmin();
    }

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
        return 'Tým';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var User $record */
        return array_filter([
            'E-mail' => $record->email,
            'Schopnosti' => $record->capabilities()->map(fn ($c) => $c->getLabel())->implode(', ') ?: null,
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->staff();
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
            StaffClientNotesRelationManager::class,
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
