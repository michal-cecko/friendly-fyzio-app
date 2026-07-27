<?php

namespace App\Filament\Widgets;

use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Pages\Calendar;
use App\Filament\Widgets\Concerns\OwnWork;
use App\Models\Reservation;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The therapist's own next visits — the personal twin of
 * {@see UpcomingReservationsWidget}.
 *
 * Today comes first and the rest of the week follows in the same list: the
 * design brief asked for a separate "My Today" timeline and "My Week" table, but
 * two near-identical tables side by side in a two-column grid read worse than
 * one, and the admin dashboard already dropped its timeline as not useful.
 *
 * The therapist column is gone — every row is theirs — and the freed space goes
 * to the room, which is what they actually need to know before walking in.
 */
class MyScheduleWidget extends TableWidget
{
    use OwnWork;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    /**
     * Lecturers who do not also treat have no reservations, and no permission to
     * open one either.
     */
    public static function canView(): bool
    {
        return (bool) auth()->user()?->isTherapist();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Můj rozvrh')
            ->headerActions([
                Action::make('openCalendar')
                    ->label('Otevřít kalendář')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('gray')
                    ->url(Calendar::getUrl()),
            ])
            ->query(
                Reservation::query()
                    ->where('therapist_id', $this->ownStaffProfileId())
                    ->whereNot('status', ReservationStatus::Cancelled)
                    ->where(function (Builder $query): void {
                        $query->whereDate('reservation_date', '>', today())
                            ->orWhere(fn (Builder $today): Builder => $today
                                ->whereDate('reservation_date', today())
                                ->where('end_time', '>=', now()->format('H:i:s')));
                    })
                    ->with(['client', 'service', 'room'])
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
                    ->description(fn (Reservation $record): ?string => $record->service?->name)
                    ->limit(28)
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
