<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourseEnrollment extends CreateRecord
{
    protected static string $resource = CourseEnrollmentResource::class;
}
