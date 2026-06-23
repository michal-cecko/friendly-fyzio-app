<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages;

use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\LessonAttendanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLessonAttendances extends ListRecords
{
    protected static string $resource = LessonAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
