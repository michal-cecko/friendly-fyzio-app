<?php

namespace App\Filament\Clusters\Provoz\Resources\Buildings\Pages;

use App\Filament\Clusters\Provoz\Resources\Buildings\BuildingResource;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBuilding extends EditRecord
{
    protected static string $resource = BuildingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
