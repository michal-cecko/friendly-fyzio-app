<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Tables;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\DeleteReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Support\Tables\TimestampColumns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('reservation_date', 'desc')
            ->columns([
                TextColumn::make('reservation_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Od'),
                TextColumn::make('client.name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('service.name')
                    ->label('Služba')
                    ->sortable(),
                TextColumn::make('therapist.user.name')
                    ->label('Terapeut'),
                TextColumn::make('room.name')
                    ->label('Místnost')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('payment_status')
                    ->label('Platba')
                    ->badge()
                    ->toggleable(),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(ReservationStatus::class),
                SelectFilter::make('payment_status')
                    ->label('Platba')
                    ->options(PaymentStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->schema(ReservationForm::components()),
                DeleteReservationAction::make(),
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
