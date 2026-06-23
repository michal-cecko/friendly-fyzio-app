<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\Workshops\Pages;

use App\Filament\Clusters\LekceWorkshopy\Resources\Workshops\WorkshopResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkshop extends ViewRecord
{
    protected static string $resource = WorkshopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
