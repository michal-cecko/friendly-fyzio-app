<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseLessons\CourseLessonResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use App\Models\CourseLesson;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCourseLesson extends ViewRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = CourseLessonResource::class;

    public function getTitle(): string
    {
        /** @var CourseLesson $record */
        $record = $this->getRecord();

        return 'Lekce kurzu '.($record->lesson_date?->format('j. n. Y') ?? '');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
