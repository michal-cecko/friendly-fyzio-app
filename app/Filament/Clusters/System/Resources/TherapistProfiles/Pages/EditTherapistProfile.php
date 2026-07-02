<?php

namespace App\Filament\Clusters\System\Resources\TherapistProfiles\Pages;

use App\Filament\Clusters\System\Resources\TherapistProfiles\TherapistProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTherapistProfile extends EditRecord
{
    protected static string $resource = TherapistProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
