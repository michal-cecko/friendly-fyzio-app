<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateCourseEnrollment extends BaseCreateRecord
{
    protected static string $resource = CourseEnrollmentResource::class;

    protected static ?string $title = 'Nová přihláška';
}
