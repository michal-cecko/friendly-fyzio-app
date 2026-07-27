<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers;

use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\CancelReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\ConfirmReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\CreateReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\MarkNoShowAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\RequestPaymentAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\ResolveDoctorNoteAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\RestoreReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\UnconfirmReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Support\Actions\RecordPaymentAction;
use App\Filament\Support\Actions\SendEmailAction;
use App\Filament\Support\Actions\SendReviewRequestAction;
use App\Models\Reservation;
use App\Support\Reservations\ReservationSummary;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ReservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'reservations';

    protected static ?string $title = 'Historie rezervací';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedCalendarDays;

    /**
     * The same booking modal lives in the client page header, so pick up
     * reservations created outside this table.
     *
     * @var array<string, string>
     */
    protected $listeners = [
        CreateReservationAction::CREATED => '$refresh',
    ];

    /**
     * Reservations are booked and managed directly from the client View page,
     * with the same action set as the Rezervace list.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->modelLabel('rezervaci')
            ->pluralModelLabel('rezervace')
            ->emptyStateHeading('Zatím žádné rezervace')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'service', 'therapist.user', 'room']))
            ->columns([
                TextColumn::make('reservation_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Od')
                    ->time('H:i')
                    // Imported visits carry a placeholder time, so the badge
                    // warns not to read anything into it.
                    ->badge(fn (Reservation $record): bool => $record->imported_at !== null)
                    ->color('gray')
                    ->tooltip(fn (Reservation $record): ?string => $record->imported_at
                        ? 'Přenesená historie – přesný čas návštěvy není znám.'
                        : null),
                TextColumn::make('end_time')
                    ->label('Do')
                    ->state(fn (Reservation $record): string => $record->endsAtIncludingBreak()->format('H:i'))
                    ->description(fn (Reservation $record): ?string => $record->breakLabel()),
                TextColumn::make('service.name')
                    ->label('Služba')
                    ->searchable(),
                TextColumn::make('therapist.user.name')
                    ->label('Terapeut')
                    ->searchable(),
                TextColumn::make('room.name')
                    ->label('Místnost')
                    ->searchable()
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
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (Reservation $record): string => ReservationResource::getUrl('view', ['record' => $record]))
            ->toolbarActions([
                CreateReservationAction::make()->client($this->getOwnerRecord()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Reservation $record): string => ReservationResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->schema(ReservationForm::components()),
                ActionGroup::make([
                    ConfirmReservationAction::make(),
                    UnconfirmReservationAction::make(),
                    SendEmailAction::make(),
                    RecordPaymentAction::make(),
                    RequestPaymentAction::make(),
                    ResolveDoctorNoteAction::make(),
                    MarkNoShowAction::make(),
                    SendReviewRequestAction::make(),
                    CancelReservationAction::make(),
                    RestoreReservationAction::make(),
                    ForceDeleteAction::make()
                        ->modalHeading('Trvale smazat rezervaci?')
                        ->modalDescription(fn (Reservation $record): HtmlString => ReservationSummary::description($record))
                        ->modalSubmitActionLabel('Trvale smazat'),
                ])
                    ->label('Další akce')
                    ->icon(Heroicon::OutlinedEllipsisHorizontal)
                    ->link()
                    ->color('gray'),
            ]);
    }
}
