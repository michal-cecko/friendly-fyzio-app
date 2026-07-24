<?php

namespace App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages;

use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\OpenPublicPageAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditEventCategory extends BaseEditRecord
{
    protected static string $resource = EventCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OpenPublicPageAction::make(),
            ViewAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
