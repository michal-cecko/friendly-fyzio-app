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
     * The whole team may look one another up: everyone on staff — therapists and
     * lecturers included — reaches the list and the read-only detail page, so a
     * colleague's contact details, capabilities and schedule are never a dead
     * link. Changing a staff account stays administrators-only
     * ({@see canManageStaff()}).
     *
     * The User model is shared with the customer-facing ClientResource, so the
     * `…:User` permission alone can't tell the two apart — every write here is
     * gated by capability on top of it.
     */
    public static function canAccess(): bool
    {
        return (auth()->user()?->isStaff() ?? false) && static::canViewAny();
    }

    /**
     * Whether the current user may change staff accounts at all. Everything that
     * writes — creating, editing, deleting, restoring — hangs off this, leaving
     * non-admin staff with a read-only view of their colleagues.
     */
    public static function canManageStaff(): bool
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
        return static::canManageStaff();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageStaff();
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User $record */
        return static::canDeleteUser($record);
    }

    public static function canDeleteAny(): bool
    {
        return static::canManageStaff();
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::canManageStaff() && parent::canForceDelete($record);
    }

    public static function canForceDeleteAny(): bool
    {
        return static::canManageStaff() && parent::canForceDeleteAny();
    }

    public static function canRestore(Model $record): bool
    {
        return static::canManageStaff() && parent::canRestore($record);
    }

    public static function canRestoreAny(): bool
    {
        return static::canManageStaff() && parent::canRestoreAny();
    }

    public static function canReplicate(Model $record): bool
    {
        return static::canManageStaff() && parent::canReplicate($record);
    }

    /**
     * An admin/super-admin account may only be deleted by a super-admin, so a
     * plain admin can't remove a peer or the owner. Everyone else stays deletable
     * by any admin — and by nobody below that tier.
     */
    public static function canDeleteUser(User $record): bool
    {
        return $record->isManageableBy(auth()->user());
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
        // Roles back both the „Schopnosti" column and the per-row write guards,
        // so they are eager-loaded rather than queried per row.
        return parent::getEloquentQuery()->staff()->with('roles');
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
