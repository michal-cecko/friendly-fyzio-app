<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages;

use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceFromPayableAction;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\OneOffEventBookingResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\RecordPaymentAction;
use App\Filament\Support\Actions\SendEmailAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOneOffEventBooking extends ViewRecord
{
    protected static string $resource = OneOffEventBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendEmailAction::make(),
            RecordPaymentAction::make(),
            GenerateInvoiceFromPayableAction::make(),
            EditAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
