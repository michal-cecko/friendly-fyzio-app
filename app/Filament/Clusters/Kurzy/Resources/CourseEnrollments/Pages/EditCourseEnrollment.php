<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\CourseEnrollment;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditCourseEnrollment extends BaseEditRecord
{
    protected static string $resource = CourseEnrollmentResource::class;

    public function getTitle(): string
    {
        /** @var CourseEnrollment $record */
        $record = $this->getRecord();

        return 'Upravit přihlášku '.($record->client?->name ?? 'bez klienta');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
