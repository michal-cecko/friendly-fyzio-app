<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients;

use App\Enums\UserRole;
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
    use ScopedToTherapist;

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', UserRole::Customer)
            // "My Clients": a therapist only sees customers they have treated.
            ->when(static::staffProfileScopeId(), fn (Builder $query, string $id) => $query
                ->whereHas('reservations', fn (Builder $reservations) => $reservations->where('therapist_id', $id)));
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
