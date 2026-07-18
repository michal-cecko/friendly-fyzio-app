<?php

namespace App\Filament\Clusters\Provoz\Resources\TherapistProfiles\Pages;

use App\Filament\Clusters\Provoz\Resources\TherapistProfiles\TherapistProfileResource;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTherapistProfile extends EditRecord
{
    protected static string $resource = TherapistProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
