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
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\RestoreReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\RestoreReservationBulkAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\SendReservationEmailAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\UnconfirmReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Support\Actions\RecordPaymentAction;
use App\Filament\Support\Actions\SendReviewRequestAction;
use App\Filament\Support\Tables\TimestampColumns;
use App\Models\Reservation;
use App\Support\Reservations\ReservationSummary;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->schema(ReservationForm::components()),
                ConfirmReservationAction::make(),
                UnconfirmReservationAction::make(),
                SendReservationEmailAction::make(),
                RecordPaymentAction::make(),
                RequestPaymentAction::make(),
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
