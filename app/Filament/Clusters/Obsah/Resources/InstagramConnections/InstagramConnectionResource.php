<?php

namespace App\Filament\Clusters\Obsah\Resources\InstagramConnections;

use App\Filament\Clusters\Obsah\ObsahCluster;
use App\Filament\Clusters\Obsah\Resources\InstagramConnections\Pages\CreateInstagramConnection;
use App\Filament\Clusters\Obsah\Resources\InstagramConnections\Pages\EditInstagramConnection;
use App\Filament\Clusters\Obsah\Resources\InstagramConnections\Pages\ListInstagramConnections;
use App\Filament\Clusters\Obsah\Resources\InstagramConnections\RelationManagers\PostsRelationManager;
use App\Filament\Clusters\Obsah\Resources\InstagramConnections\Schemas\InstagramConnectionForm;
use App\Filament\Clusters\Obsah\Resources\InstagramConnections\Tables\InstagramConnectionsTable;
use App\Jobs\SyncInstagramConnectionJob;
use App\Models\InstagramConnection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InstagramConnectionResource extends Resource
{
    protected static ?string $model = InstagramConnection::class;

    protected static ?string $cluster = ObsahCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'username';

    public static function getModelLabel(): string
    {
        return 'Instagram účet';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Instagram účty';
    }

    public static function getNavigationLabel(): string
    {
        return 'Instagram účty';
    }

    public static function form(Schema $schema): Schema
    {
        return InstagramConnectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstagramConnectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PostsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstagramConnections::route('/'),
            'create' => CreateInstagramConnection::route('/create'),
            'edit' => EditInstagramConnection::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Redirects to the OAuth handshake to (re)connect the Instagram account.
     * Reused by both the table row and the edit-page header.
     */
    public static function authorizeAction(): Action
    {
        return Action::make('authorize')
            ->label(fn (InstagramConnection $record): string => $record->needsReauthorization() ? 'Autorizovat' : 'Znovu autorizovat')
            ->icon(Heroicon::ArrowTopRightOnSquare)
            ->color('primary')
            // Open in a new tab so the panel's SPA (wire:navigate) does not AJAX-follow
            // the redirect to instagram.com (which the browser blocks as cross-origin/CORS).
            ->url(fn (InstagramConnection $record): string => route('instagram.oauth.redirect', $record))
            ->openUrlInNewTab();
    }

    /**
     * Queues an immediate sync of the connection's recent posts.
     */
    public static function syncAction(): Action
    {
        return Action::make('sync')
            ->label('Synchronizovat')
            ->icon(Heroicon::ArrowPath)
            ->color('gray')
            ->visible(fn (InstagramConnection $record): bool => ! $record->needsReauthorization())
            ->action(function (InstagramConnection $record): void {
                SyncInstagramConnectionJob::dispatch($record);

                Notification::make()
                    ->title('Synchronizace byla zařazena.')
                    ->success()
                    ->send();
            });
    }
}
