<?php

namespace App\Filament\Clusters\System\Resources\TherapistProfiles\Pages;

use App\Filament\Clusters\System\Resources\TherapistProfiles\TherapistProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTherapistProfiles extends ListRecords
{
    protected static string $resource = TherapistProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
