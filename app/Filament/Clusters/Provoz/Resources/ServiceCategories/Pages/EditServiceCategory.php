<?php

namespace App\Filament\Clusters\Provoz\Resources\ServiceCategories\Pages;

use App\Filament\Clusters\Provoz\Resources\ServiceCategories\ServiceCategoryResource;
use App\Filament\Support\Actions\OpenPublicPageAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceCategory extends EditRecord
{
    protected static string $resource = ServiceCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OpenPublicPageAction::make(),
            DeleteAction::make(),
        ];
    }
}
