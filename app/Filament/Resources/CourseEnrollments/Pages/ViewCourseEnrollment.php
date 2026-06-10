<?php

namespace App\Filament\Resources\CourseEnrollments\Pages;

use App\Filament\Resources\CourseEnrollments\CourseEnrollmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCourseEnrollment extends ViewRecord
{
    protected static string $resource = CourseEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
