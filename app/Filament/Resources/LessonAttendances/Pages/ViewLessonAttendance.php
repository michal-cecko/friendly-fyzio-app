<?php

namespace App\Filament\Resources\LessonAttendances\Pages;

use App\Filament\Resources\LessonAttendances\LessonAttendanceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLessonAttendance extends ViewRecord
{
    protected static string $resource = LessonAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
