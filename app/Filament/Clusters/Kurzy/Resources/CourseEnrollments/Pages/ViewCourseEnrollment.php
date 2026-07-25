<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages;

use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceFromPayableAction;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\RecordPaymentAction;
use App\Filament\Support\Actions\SendEmailAction;
use App\Models\CourseEnrollment;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCourseEnrollment extends ViewRecord
{
    protected static string $resource = CourseEnrollmentResource::class;

    public function getTitle(): string
    {
        /** @var CourseEnrollment $record */
        $record = $this->getRecord();

        return 'Přihláška '.($record->client?->name ?? 'bez klienta');
    }

    protected function getHeaderActions(): array
    {
        return [
            SendEmailAction::make(),
            RecordPaymentAction::make(),
            GenerateInvoiceFromPayableAction::make(),
            EditAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
