<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients;

use App\Filament\Clusters\Provoz\ProvozCluster;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\CreateClient;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\EditClient;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ListClients;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ViewClient;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\CourseEnrollmentsRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\CreditTransactionsRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\InvoicesRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\NotesRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\ReservationsRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\SubstituteTokensRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\Schemas\ClientForm;
use App\Filament\Clusters\Provoz\Resources\Clients\Schemas\ClientInfolist;
use App\Filament\Clusters\Provoz\Resources\Clients\Tables\ClientsTable;
use App\Filament\Support\Concerns\ScopedToTherapist;
use App\Filament\Support\RelationManagers\PaymentsRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $cluster = ProvozCluster::class;

    protected static ?string $slug = 'clients';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 10;

    public static function getModelLabel(): string
    {
        return 'klient';
    }

    public static function getPluralModelLabel(): string
    {
        return 'klienti';
    }

    public static function getNavigationLabel(): string
    {
        return 'Klienti';
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
            'Telefon' => $record->phone,
        ]);
    }

    /**
     * The client base is shared: every staff member sees all customers, not just
     * the ones they have personally treated — a colleague covering a visit needs
     * the client's history in front of them. Unlike the other shared resources,
     * this one carries no {@see ScopedToTherapist} row scope.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->customers();
    }

    /**
     * Customers are any staff member's to manage — therapists and lecturers may
     * create, edit and delete them. The guard only bites on the rare account
     * that is both customer and staff: changing a colleague belongs in the Tým
     * resource, under its admin-only rules. See {@see User::isManageableBy()}.
     */
    public static function canManageClient(User $record): bool
    {
        return $record->isManageableBy(auth()->user());
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User $record */
        return parent::canEdit($record) && static::canManageClient($record);
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User $record */
        return parent::canDelete($record) && static::canManageClient($record);
    }

    public static function canForceDelete(Model $record): bool
    {
        /** @var User $record */
        return parent::canForceDelete($record) && static::canManageClient($record);
    }

    public static function canRestore(Model $record): bool
    {
        /** @var User $record */
        return parent::canRestore($record) && static::canManageClient($record);
    }

    public static function form(Schema $schema): Schema
    {
        return ClientForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClientInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            NotesRelationManager::class,
            ReservationsRelationManager::class,
            CourseEnrollmentsRelationManager::class,
            CreditTransactionsRelationManager::class,
            PaymentsRelationManager::class,
            InvoicesRelationManager::class,
            SubstituteTokensRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'view' => ViewClient::route('/{record}'),
            'edit' => EditClient::route('/{record}/edit'),
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
