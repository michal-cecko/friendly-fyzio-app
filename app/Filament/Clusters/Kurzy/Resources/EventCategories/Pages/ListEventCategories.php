<?php

namespace App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages;

use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventCategories extends ListRecords
{
    protected static string $resource = EventCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
