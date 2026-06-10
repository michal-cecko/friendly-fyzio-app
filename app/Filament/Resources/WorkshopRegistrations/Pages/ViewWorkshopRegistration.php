<?php

namespace App\Filament\Resources\WorkshopRegistrations\Pages;

use App\Filament\Resources\WorkshopRegistrations\WorkshopRegistrationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkshopRegistration extends ViewRecord
{
    protected static string $resource = WorkshopRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
