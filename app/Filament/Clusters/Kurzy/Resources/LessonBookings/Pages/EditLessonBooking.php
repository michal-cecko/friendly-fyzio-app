<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages;

use App\Filament\Clusters\Kurzy\Resources\LessonBookings\LessonBookingResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Concerns\HasCourseBreadcrumbs;
use App\Models\LessonBooking;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditLessonBooking extends BaseEditRecord
{
    use HasCourseBreadcrumbs;

    protected static string $resource = LessonBookingResource::class;

    public function getTitle(): string
    {
        /** @var LessonBooking $record */
        $record = $this->getRecord();

        return 'Upravit přihlášku na akci '.($record->client?->name ?? 'bez klienta');
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
