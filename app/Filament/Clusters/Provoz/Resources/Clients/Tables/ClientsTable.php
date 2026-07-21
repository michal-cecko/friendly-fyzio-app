<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\Tables;

use App\Filament\Support\Actions\ReactivateUserAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use STS\FilamentImpersonate\Actions\Impersonate;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('clientProfile')
                ->withMax('reservations as last_reservation_at', 'reservation_date'))
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
                TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('clientProfile.gender')
                    ->label('Pohlaví')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('last_reservation_at')
                    ->label('Poslední rezervace')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('email_verified_at')
                    ->label('Ověřen email?')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('deactivated_at')
                    ->label('Stav')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn (): string => 'Deaktivováno')
                    ->placeholder('Aktivní'),

                // Additional columns — hidden by default, admins can toggle them on.
                TextColumn::make('clientProfile.date_of_birth')
                    ->label('Datum narození')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clientProfile.address_city')
                    ->label('Město')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clientProfile.occupation')
                    ->label('Povolání')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clientProfile.weight')
                    ->label('Váha (kg)')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clientProfile.height')
                    ->label('Výška (cm)')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clientProfile.billing_name')
                    ->label('Fakturační jméno')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clientProfile.company_ico')
                    ->label('IČO')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clientProfile.company_dic')
                    ->label('DIČ')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('newsletter_opted_in_at')
                    ->label('Newsletter')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Registrován')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Naposledy upraveno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
                ViewAction::make(),
                EditAction::make(),
                ReactivateUserAction::make(),
                Impersonate::make(),
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
