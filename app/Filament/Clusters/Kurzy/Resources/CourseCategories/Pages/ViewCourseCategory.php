<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\CourseCategoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCourseCategory extends ViewRecord
{
    protected static string $resource = CourseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
