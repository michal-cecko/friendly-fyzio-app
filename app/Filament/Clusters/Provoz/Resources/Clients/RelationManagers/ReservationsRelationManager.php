<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'reservations';

    protected static ?string $title = 'Historie rezervací';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedCalendarDays;

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['service', 'therapist.user', 'room']))
            ->columns([
                TextColumn::make('reservation_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Od')
                    // Imported visits carry a placeholder time, so the badge
                    // warns not to read anything into it.
                    ->badge(fn (Reservation $record): bool => $record->imported_at !== null)
                    ->color('gray')
                    ->tooltip(fn (Reservation $record): ?string => $record->imported_at
                        ? 'Přenesená historie – přesný čas návštěvy není znám.'
                        : null),
                TextColumn::make('service.name')
                    ->label('Služba'),
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
