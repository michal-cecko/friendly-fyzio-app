<?php

namespace App\Filament\Clusters\Provoz\Resources\Users\RelationManagers;

use App\Enums\ReservationStatus;
use App\Filament\Pages\Calendar;
use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TherapistReservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'therapistReservations';

    protected static ?string $title = 'Rezervace terapeuta';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User && $ownerRecord->isTherapist();
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
                    ->label('Od')
                    ->time('H:i'),
                TextColumn::make('end_time')
                    ->label('Do')
                    ->state(fn (Reservation $record): string => $record->endsAtIncludingBreak()->format('H:i'))
                    ->description(fn (Reservation $record): ?string => $record->breakLabel()),
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
            ->headerActions([
                Action::make('viewInCalendar')
                    ->label('Otevřít v kalendáři')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('gray')
                    ->visible(fn (): bool => $this->getOwnerRecord()->staffProfile !== null)
                    ->url(fn (): string => Calendar::getUrl([
                        'therapists' => [$this->getOwnerRecord()->staffProfile->getKey()],
                    ])),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
