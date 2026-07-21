<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages;

use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\OneOffEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOneOffEvents extends ListRecords
{
    protected static string $resource = OneOffEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
