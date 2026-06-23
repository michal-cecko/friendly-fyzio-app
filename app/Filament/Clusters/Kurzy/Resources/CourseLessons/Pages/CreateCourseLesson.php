<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseLessons\CourseLessonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourseLesson extends CreateRecord
{
    protected static string $resource = CourseLessonResource::class;
}
