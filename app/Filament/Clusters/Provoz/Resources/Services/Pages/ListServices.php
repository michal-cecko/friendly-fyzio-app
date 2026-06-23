<?php

namespace App\Filament\Clusters\Provoz\Resources\Services\Pages;

use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServices extends ListRecords
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
