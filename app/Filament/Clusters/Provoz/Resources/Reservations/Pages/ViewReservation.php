<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Pages;

use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceFromPayableAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\CancelReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\ConfirmReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\MarkNoShowAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\RequestPaymentAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\ResolveDoctorNoteAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\RestoreReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\UnconfirmReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\RecordPaymentAction;
use App\Filament\Support\Actions\SendEmailAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConfirmReservationAction::make(),
            // A full edit page (not a modal): the notes RichEditor's mention menu
            // throws inside Filament modals, so editing lives on its own page.
            EditAction::make(),
            ActionGroup::make([
                RecordPaymentAction::make(),
                RequestPaymentAction::make(),
                ResolveDoctorNoteAction::make(),
                GenerateInvoiceFromPayableAction::make(),
            ])
                ->label('Platby')
                ->icon(Heroicon::OutlinedBanknotes)
                ->button()
                ->color('gray'),
            ActionGroup::make([
                SendEmailAction::make(),
                UnconfirmReservationAction::make(),
                MarkNoShowAction::make(),
                CancelReservationAction::make(),
                RestoreReservationAction::make(),
                ForceDeleteAction::make(),
                ActivityLogAction::make(),
            ])
                ->label('Další')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->button()
                ->color('gray'),
        ];
    }
}
