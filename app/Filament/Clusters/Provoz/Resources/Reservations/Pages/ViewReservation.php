<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Pages;

use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceFromPayableAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\CancelReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\ConfirmReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\MarkNoShowAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\RequestPaymentAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\RestoreReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\SendReservationEmailAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\UnconfirmReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\RecordPaymentAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConfirmReservationAction::make(),
            UnconfirmReservationAction::make(),
            SendReservationEmailAction::make(),
            RecordPaymentAction::make(),
            RequestPaymentAction::make(),
            GenerateInvoiceFromPayableAction::make(),
            MarkNoShowAction::make(),
            // A full edit page (not a modal): the notes RichEditor's mention menu
            // throws inside Filament modals, so editing lives on its own page.
            EditAction::make(),
            CancelReservationAction::make(),
            RestoreReservationAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
