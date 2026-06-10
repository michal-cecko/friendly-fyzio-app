<?php

namespace App\Filament\Resources\CourseLessons\Pages;

use App\Filament\Resources\CourseLessons\CourseLessonResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCourseLesson extends ViewRecord
{
    protected static string $resource = CourseLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
