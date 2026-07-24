<?php

namespace App\Filament\Clusters\Provoz\Resources\Buildings\Pages;

use App\Filament\Clusters\Provoz\Resources\Buildings\BuildingResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\DeleteAction;

class EditBuilding extends BaseEditRecord
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
