<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Pages;

use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceFromPayableAction;
use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\OneTimeLessonBookingResource;
use App\Filament\Support\Actions\RecordPaymentAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOneTimeLessonBooking extends ViewRecord
{
    protected static string $resource = OneTimeLessonBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecordPaymentAction::make(),
            GenerateInvoiceFromPayableAction::make(),
            EditAction::make(),
        ];
    }
}
