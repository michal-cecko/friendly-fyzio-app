<?php

namespace App\Filament\Clusters\Provoz\Resources\ServiceCategories\Pages;

use App\Filament\Clusters\Provoz\Resources\ServiceCategories\ServiceCategoryResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\OpenPublicPageAction;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use App\Models\ServiceCategory;
use Filament\Actions\DeleteAction;

class EditServiceCategory extends BaseEditRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = ServiceCategoryResource::class;

    public function getTitle(): string
    {
        /** @var ServiceCategory $record */
        $record = $this->getRecord();

        return 'Upravit kategorii služby '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            OpenPublicPageAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
