<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Pages;

use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\OneTimeLessonBookingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOneTimeLessonBooking extends ViewRecord
{
    protected static string $resource = OneTimeLessonBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
