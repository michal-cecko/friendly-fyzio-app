<?php

namespace App\Filament\Resources\OneTimeLessonBookings\Pages;

use App\Filament\Resources\OneTimeLessonBookings\OneTimeLessonBookingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOneTimeLessonBooking extends CreateRecord
{
    protected static string $resource = OneTimeLessonBookingResource::class;
}
