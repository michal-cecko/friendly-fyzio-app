<?php

namespace App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages;

use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateEventCategory extends BaseCreateRecord
{
    protected static string $resource = EventCategoryResource::class;
}
