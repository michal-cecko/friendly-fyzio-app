<?php

namespace App\Filament\Clusters\Provoz\Resources\Users\Tables;

use App\Enums\Capability;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Support\Actions\ReactivateUserAction;
use App\Filament\Support\Tables\TimestampColumns;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Jméno')
                    ->state(fn (User $record): string => $record->full_name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon(Heroicon::OutlinedEnvelope),
                TextColumn::make('capabilities')
                    ->label('Schopnosti')
                    ->badge()
                    ->state(fn (User $record): array => $record->capabilities()->all())
                    ->placeholder('—'),
                IconColumn::make('email_verified_at')
                    ->label('Ověřen email?')
                    ->boolean()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('capability')
                    ->label('Schopnost')
                    ->options(collect(Capability::cases())
                        ->mapWithKeys(fn (Capability $c): array => [$c->value => $c->getLabel()])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $value) => $query->whereHas(
                            'roles',
                            fn (Builder $roles) => $roles->where('name', Capability::from($value)->roleName()),
                        ),
                    )),
                TernaryFilter::make('deactivated')
                    ->label('Deaktivované')
                    ->placeholder('Vše')
                    ->trueLabel('Jen deaktivované')
                    ->falseLabel('Jen aktivní')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('deactivated_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('deactivated_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Non-admin staff read the team roster and nothing more, and
                // Filament does not authorize action buttons against the
                // resource on its own — every write is gated explicitly.
                EditAction::make()
                    ->visible(fn (): bool => UserResource::canManageStaff()),
                // Deactivating cancels live bookings, so it is left to the detail
                // pages where the whole account is in view — the row only offers
                // the way back for an account that is already deactivated.
                ReactivateUserAction::make(),
                // Overriding ->visible() replaces Filament's own condition, so each
                // of these has to repeat the trashed state it belongs to.
                DeleteAction::make()
                    ->visible(fn (User $record): bool => ! $record->trashed() && UserResource::canDeleteUser($record)),
                RestoreAction::make()
                    ->visible(fn (User $record): bool => $record->trashed() && UserResource::canManageStaff()),
                ForceDeleteAction::make()
                    ->visible(fn (User $record): bool => $record->trashed() && UserResource::canManageStaff()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Smazat vybrané uživatele'),
                    ForceDeleteBulkAction::make()
                        ->modalHeading('Trvale smazat vybrané uživatele'),
                    RestoreBulkAction::make()
                        ->modalHeading('Obnovit vybrané uživatele'),
                ])
                    ->visible(fn (): bool => UserResource::canManageStaff()),
            ]);
    }
}
