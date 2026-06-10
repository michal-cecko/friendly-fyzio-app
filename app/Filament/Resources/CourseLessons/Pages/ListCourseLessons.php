<?php

namespace App\Filament\Resources\CourseLessons\Pages;

use App\Filament\Resources\CourseLessons\CourseLessonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourseLessons extends ListRecords
{
    protected static string $resource = CourseLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
