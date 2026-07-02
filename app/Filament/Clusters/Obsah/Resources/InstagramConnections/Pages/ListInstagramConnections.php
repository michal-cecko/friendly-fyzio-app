<?php

namespace App\Filament\Clusters\Obsah\Resources\InstagramConnections\Pages;

use App\Filament\Clusters\Obsah\Resources\InstagramConnections\InstagramConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstagramConnections extends ListRecords
{
    protected static string $resource = InstagramConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
