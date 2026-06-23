<?php

namespace App\Filament\Clusters\System\Resources\Users\RelationManagers;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TherapistReservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'therapistReservations';

    protected static ?string $title = 'Rezervace (jako terapeut)';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User && $ownerRecord->role === UserRole::Therapist;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'service', 'room']))
            ->columns([
                TextColumn::make('reservation_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Od'),
                TextColumn::make('client.name')
                    ->label('Klient')
                    ->searchable(),
                TextColumn::make('service.name')
                    ->label('Služba'),
                TextColumn::make('room.name')
                    ->label('Místnost')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('payment_status')
                    ->label('Platba')
                    ->badge(),
            ])
            ->defaultSort('reservation_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(ReservationStatus::class),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
