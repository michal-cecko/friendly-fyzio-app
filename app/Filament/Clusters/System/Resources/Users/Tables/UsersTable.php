<?php

namespace App\Filament\Clusters\System\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Filament\Support\Tables\TimestampColumns;
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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Jméno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon(Heroicon::OutlinedEnvelope),
                TextColumn::make('role')
                    ->label('Typ účtu')
                    ->badge()
                    ->sortable(),
                IconColumn::make('email_verified_at')
                    ->label('Ověřen email?')
                    ->boolean()
                    ->toggleable(),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Typ účtu')
                    ->options(collect(UserRole::cases())
                        ->reject(fn (UserRole $role): bool => $role === UserRole::Customer)
                        ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->getLabel()])
                        ->all()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
