<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages;

use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\LessonAttendanceResource;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateLessonAttendance extends BaseCreateRecord
{
    protected static string $resource = LessonAttendanceResource::class;

    protected static ?string $title = 'Nová docházka';
}
