<?php

namespace App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages;

use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEventCategory extends CreateRecord
{
    protected static string $resource = EventCategoryResource::class;
}
