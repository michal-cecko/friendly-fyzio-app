<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\Workshops\Pages;

use App\Filament\Clusters\LekceWorkshopy\Resources\Workshops\WorkshopResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkshops extends ListRecords
{
    protected static string $resource = WorkshopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
