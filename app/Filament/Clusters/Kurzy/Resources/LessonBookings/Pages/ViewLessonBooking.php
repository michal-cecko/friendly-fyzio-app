<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages;

use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceFromPayableAction;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\LessonBookingResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\RecordPaymentAction;
use App\Filament\Support\Actions\SendEmailAction;
use App\Filament\Support\Concerns\HasCourseBreadcrumbs;
use App\Models\LessonBooking;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLessonBooking extends ViewRecord
{
    use HasCourseBreadcrumbs;

    protected static string $resource = LessonBookingResource::class;

    public function getTitle(): string
    {
        /** @var LessonBooking $record */
        $record = $this->getRecord();

        return 'Přihláška na akci '.($record->client?->name ?? 'bez klienta');
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
