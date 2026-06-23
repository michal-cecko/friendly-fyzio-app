<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses\Pages;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourse extends CreateRecord
{
    protected static string $resource = CourseResource::class;
}
