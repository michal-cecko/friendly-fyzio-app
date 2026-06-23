<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\Tables;

use App\Enums\PaymentStatus;
use App\Filament\Support\Tables\TimestampColumns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkshopRegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workshop.name')
                    ->label('Workshop')
                    ->searchable(),
                TextColumn::make('client.name')
                    ->label('Klient')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('payment_status')
                    ->label('Platba')
                    ->badge(),
                TextColumn::make('paid_at')
                    ->label('Zaplaceno')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Platba')
                    ->options(PaymentStatus::class),
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(['confirmed' => 'Potvrzeno', 'pending' => 'Čeká', 'cancelled' => 'Zrušeno']),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
