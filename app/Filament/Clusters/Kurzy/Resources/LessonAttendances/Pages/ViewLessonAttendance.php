<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages;

use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\LessonAttendanceResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\EditExcuseAction;
use App\Filament\Support\Concerns\HasCourseBreadcrumbs;
use App\Models\LessonAttendance;
use Filament\Resources\Pages\ViewRecord;

class ViewLessonAttendance extends ViewRecord
{
    use HasCourseBreadcrumbs;

    protected static string $resource = LessonAttendanceResource::class;

    public function getTitle(): string
    {
        /** @var LessonAttendance $record */
        $record = $this->getRecord();

        return 'Docházka '.($record->client?->name ?? 'bez klienta');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditExcuseAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
