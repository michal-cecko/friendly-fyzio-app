<?php

namespace App\Filament\Clusters\Provoz\Resources\Specializations\Pages;

use App\Filament\Clusters\Provoz\Resources\Specializations\SpecializationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpecializations extends ListRecords
{
    protected static string $resource = SpecializationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
