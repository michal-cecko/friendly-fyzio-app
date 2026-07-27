<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages;

use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\LessonAttendanceResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Docházka across every lesson at once — a search surface, not a workbench. A
 * single lesson's roster is managed on that lesson's own page, and seats are
 * created by enrolling or by buying one, never from here.
 */
class ListLessonAttendances extends ListRecords
{
    protected static string $resource = LessonAttendanceResource::class;
}
