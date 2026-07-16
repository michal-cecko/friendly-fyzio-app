<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\Pages;

use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceFromPayableAction;
use App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\WorkshopRegistrationResource;
use App\Filament\Support\Actions\RecordPaymentAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkshopRegistration extends ViewRecord
{
    protected static string $resource = WorkshopRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecordPaymentAction::make(),
            GenerateInvoiceFromPayableAction::make(),
            EditAction::make(),
        ];
    }
}
