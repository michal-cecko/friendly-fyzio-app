<?php

namespace App\Filament\Clusters\Workshopy\Resources\WorkshopRegistrations\Pages;

use App\Filament\Clusters\Workshopy\Resources\WorkshopRegistrations\WorkshopRegistrationResource;
use App\Filament\Support\Actions\ActivityLogAction;
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
            ActivityLogAction::make(),
        ];
    }
}
