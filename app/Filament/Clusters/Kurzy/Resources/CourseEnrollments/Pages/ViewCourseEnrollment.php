<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
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
