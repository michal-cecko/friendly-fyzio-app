<?php

namespace App\Filament\Resources\LessonAttendances\Pages;

use App\Filament\Resources\LessonAttendances\LessonAttendanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLessonAttendance extends CreateRecord
{
    protected static string $resource = LessonAttendanceResource::class;
}
