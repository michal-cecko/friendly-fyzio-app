<?php

namespace App\Filament\Clusters\Provoz\Resources\ServiceCategories\Pages;

use App\Filament\Clusters\Provoz\Resources\ServiceCategories\ServiceCategoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceCategory extends ViewRecord
{
    protected static string $resource = ServiceCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
