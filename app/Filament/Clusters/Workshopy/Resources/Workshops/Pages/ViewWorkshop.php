<?php

namespace App\Filament\Clusters\Workshopy\Resources\Workshops\Pages;

use App\Filament\Clusters\Workshopy\Resources\Workshops\WorkshopResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkshop extends ViewRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = WorkshopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
