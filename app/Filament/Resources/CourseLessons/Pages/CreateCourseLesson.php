<?php

namespace App\Filament\Resources\CourseLessons\Pages;

use App\Filament\Resources\CourseLessons\CourseLessonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourseLesson extends CreateRecord
{
    protected static string $resource = CourseLessonResource::class;
}
