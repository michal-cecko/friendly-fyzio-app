<?php

namespace App\Filament\Widgets;

use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Pages\Calendar;
use App\Filament\Widgets\Concerns\AdminOnly;
use App\Models\Reservation;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingReservationsWidget extends TableWidget
{
    use AdminOnly;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Nejbližší rezervace')
            ->headerActions([
                Action::make('openCalendar')
                    ->label('Otevřít kalendář')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('gray')
                    ->url(Calendar::getUrl()),
            ])
            ->query(
                Reservation::query()
                    ->whereNot('status', ReservationStatus::Cancelled)
                    ->where(function (Builder $query): void {
                        $query->whereDate('reservation_date', '>', today())
                            ->orWhere(fn (Builder $today): Builder => $today
                                ->whereDate('reservation_date', today())
                                ->where('end_time', '>=', now()->format('H:i:s')));
                    })
                    ->with(['client', 'service', 'therapist.user', 'room'])
                    ->orderBy('reservation_date')
                    ->orderBy('start_time')
                    ->limit(5),
            )
            ->columns([
                TextColumn::make('reservation_date')
                    ->label('Kdy')
                    ->formatStateUsing(fn (Reservation $record): string => $record->reservation_date->format('d.m.')
                        .' '.substr((string) $record->start_time, 0, 5)
                        .'–'.substr((string) $record->end_time, 0, 5))
                    ->weight('semibold'),
                TextColumn::make('client.name')
                    ->label('Klient')
                    ->placeholder('—'),
                TextColumn::make('service.name')
                    ->label('Služba')
                    ->limit(28)
                    ->placeholder('—'),
                TextColumn::make('therapist.user.name')
                    ->label('Terapeut')
                    ->placeholder('—'),
                TextColumn::make('room.name')
                    ->label('Místnost')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
            ])
            ->recordUrl(fn (Reservation $record): string => ReservationResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->emptyStateHeading('Žádné nadcházející rezervace')
            ->emptyStateIcon(Heroicon::OutlinedCalendar);
    }
}
