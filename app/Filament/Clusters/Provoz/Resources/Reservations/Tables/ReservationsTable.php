<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Tables;

use App\Enums\ConfirmationSource;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\CancelReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\CancelReservationBulkAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\ConfirmReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\MarkNoShowAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\RequestPaymentAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\ResolveDoctorNoteAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\RestoreReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\RestoreReservationBulkAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\UnconfirmReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Support\Actions\RecordPaymentAction;
use App\Filament\Support\Actions\SendEmailAction;
use App\Filament\Support\Actions\SendReviewRequestAction;
use App\Filament\Support\Tables\TimestampColumns;
use App\Models\Reservation;
use App\Support\Reservations\ReservationSummary;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

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
                TextColumn::make('confirmed_by')
                    ->label('Potvrdil')
                    ->badge()
                    ->toggleable(),
                IconColumn::make('doctor_note_requested_at')
                    ->label('Lékař. potvrzení')
                    ->tooltip('Klient přislíbil potvrzení od lékaře (storno poplatek pozastaven)')
                    ->icon(fn ($state): string|BackedEnum|null => $state !== null ? Heroicon::OutlinedDocumentText : null)
                    ->color('warning')
                    ->toggleable(),
                IconColumn::make('settled_at')
                    ->label('Vybaveno')
                    ->tooltip('Vyřízeno – proběhlo a uhrazeno (nebo storno vyřešeno)')
                    ->icon(fn ($state): string|BackedEnum|null => $state !== null ? Heroicon::CheckCircle : null)
                    ->color('success'),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(ReservationStatus::class),
                SelectFilter::make('payment_status')
                    ->label('Platba')
                    ->options(PaymentStatus::class),
                SelectFilter::make('confirmed_by')
                    ->label('Potvrdil')
                    ->options(ConfirmationSource::class),
                Filter::make('doctor_note_pending')
                    ->label('Čeká na potvrzení od lékaře')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('doctor_note_requested_at')
                        ->whereNull('doctor_note_resolved_at'))
                    ->toggle(),
                Filter::make('settled')
                    ->label('Vybaveno')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('settled_at'))
                    ->toggle(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->schema(ReservationForm::components()),
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
            ->toolbarActions([
                BulkActionGroup::make([
                    CancelReservationBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreReservationBulkAction::make(),
                ]),
            ]);
    }
}
