<?php

namespace App\Filament\Clusters\Provoz\Resources\ServiceCategories\Pages;

use App\Filament\Clusters\Provoz\Resources\ServiceCategories\ServiceCategoryResource;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateServiceCategory extends BaseCreateRecord
{
    protected static string $resource = ServiceCategoryResource::class;

    protected static ?string $title = 'Nová kategorie služby';
}
