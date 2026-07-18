<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseLessons\CourseLessonResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Concerns\NotifiesScheduleChange;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCourseLesson extends EditRecord
{
    use NotifiesScheduleChange;

    protected static string $resource = CourseLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function scheduleAttributes(): array
    {
        return ['lesson_date', 'start_time', 'end_time', 'room_id'];
    }
}
