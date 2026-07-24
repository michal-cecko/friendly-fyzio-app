<?php

namespace App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages;

use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\OpenPublicPageAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEventCategory extends ViewRecord
{
    protected static string $resource = EventCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OpenPublicPageAction::make(),
            EditAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
