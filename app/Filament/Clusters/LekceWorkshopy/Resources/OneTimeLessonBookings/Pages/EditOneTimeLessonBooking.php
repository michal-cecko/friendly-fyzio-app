<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Pages;

use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\OneTimeLessonBookingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOneTimeLessonBooking extends EditRecord
{
    protected static string $resource = OneTimeLessonBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
