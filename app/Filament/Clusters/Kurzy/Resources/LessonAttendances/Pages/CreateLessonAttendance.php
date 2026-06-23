<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages;

use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\LessonAttendanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLessonAttendance extends CreateRecord
{
    protected static string $resource = LessonAttendanceResource::class;
}
