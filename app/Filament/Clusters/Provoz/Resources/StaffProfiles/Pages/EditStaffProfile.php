<?php

namespace App\Filament\Clusters\Provoz\Resources\StaffProfiles\Pages;

use App\Filament\Clusters\Provoz\Resources\StaffProfiles\StaffProfileResource;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStaffProfile extends EditRecord
{
    protected static string $resource = StaffProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
