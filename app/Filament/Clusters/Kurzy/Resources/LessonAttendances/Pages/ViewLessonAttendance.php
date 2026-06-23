<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages;

use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\LessonAttendanceResource;
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
