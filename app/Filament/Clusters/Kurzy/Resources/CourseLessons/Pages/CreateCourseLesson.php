<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseLessons\CourseLessonResource;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateCourseLesson extends BaseCreateRecord
{
    protected static string $resource = CourseLessonResource::class;
}
