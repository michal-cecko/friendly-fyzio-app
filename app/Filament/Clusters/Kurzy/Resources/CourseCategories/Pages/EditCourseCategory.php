<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\CourseCategoryResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditCourseCategory extends BaseEditRecord
{
    protected static string $resource = CourseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
