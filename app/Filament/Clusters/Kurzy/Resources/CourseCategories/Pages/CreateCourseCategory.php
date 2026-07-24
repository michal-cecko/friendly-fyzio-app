<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\CourseCategoryResource;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateCourseCategory extends BaseCreateRecord
{
    protected static string $resource = CourseCategoryResource::class;
}
