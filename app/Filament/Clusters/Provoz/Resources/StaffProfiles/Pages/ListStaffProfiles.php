<?php

namespace App\Filament\Clusters\Provoz\Resources\StaffProfiles\Pages;

use App\Filament\Clusters\Provoz\Resources\StaffProfiles\StaffProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaffProfiles extends ListRecords
{
    protected static string $resource = StaffProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
