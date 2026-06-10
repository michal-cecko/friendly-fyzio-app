<?php

namespace App\Filament\Resources\CourseEnrollments\Pages;

use App\Filament\Resources\CourseEnrollments\CourseEnrollmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourseEnrollments extends ListRecords
{
    protected static string $resource = CourseEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
