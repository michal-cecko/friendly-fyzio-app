<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\Pages;

use App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\WorkshopRegistrationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkshopRegistration extends EditRecord
{
    protected static string $resource = WorkshopRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
