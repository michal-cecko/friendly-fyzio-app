<?php

namespace App\Filament\Clusters\Provoz\Resources\ServiceCategories\Pages;

use App\Filament\Clusters\Provoz\Resources\ServiceCategories\ServiceCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceCategories extends ListRecords
{
    protected static string $resource = ServiceCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
