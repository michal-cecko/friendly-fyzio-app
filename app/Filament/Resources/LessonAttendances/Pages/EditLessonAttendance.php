<?php

namespace App\Filament\Resources\LessonAttendances\Pages;

use App\Filament\Resources\LessonAttendances\LessonAttendanceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLessonAttendance extends EditRecord
{
    protected static string $resource = LessonAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
