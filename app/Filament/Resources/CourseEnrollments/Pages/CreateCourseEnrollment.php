<?php

namespace App\Filament\Resources\CourseEnrollments\Pages;

use App\Filament\Resources\CourseEnrollments\CourseEnrollmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourseEnrollment extends CreateRecord
{
    protected static string $resource = CourseEnrollmentResource::class;
}
