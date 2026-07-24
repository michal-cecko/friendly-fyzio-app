<?php

namespace App\Filament\Clusters\Provoz\Resources\ServiceCategories\Pages;

use App\Filament\Clusters\Provoz\Resources\ServiceCategories\ServiceCategoryResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceCategory extends ViewRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = ServiceCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
