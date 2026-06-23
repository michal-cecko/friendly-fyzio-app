<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Pages;

use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\OneTimeLessonBookingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOneTimeLessonBooking extends CreateRecord
{
    protected static string $resource = OneTimeLessonBookingResource::class;
}
