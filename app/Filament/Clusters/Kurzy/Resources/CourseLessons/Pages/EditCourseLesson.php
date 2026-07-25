<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseLessons\CourseLessonResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Concerns\NotifiesScheduleChange;
use App\Models\CourseLesson;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditCourseLesson extends BaseEditRecord
{
    use NotifiesScheduleChange;

    protected static string $resource = CourseLessonResource::class;

    public function getTitle(): string
    {
        /** @var CourseLesson $record */
        $record = $this->getRecord();

        return 'Upravit lekci kurzu '.($record->lesson_date?->format('j. n. Y') ?? '');
    }

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
