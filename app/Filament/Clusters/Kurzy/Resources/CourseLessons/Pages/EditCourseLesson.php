<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseLessons\CourseLessonResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCourseLesson extends EditRecord
{
    protected static string $resource = CourseLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
