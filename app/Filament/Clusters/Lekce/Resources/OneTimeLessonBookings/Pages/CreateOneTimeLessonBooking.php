<?php

namespace App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\Pages;

use App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\OneTimeLessonBookingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOneTimeLessonBooking extends CreateRecord
{
    protected static string $resource = OneTimeLessonBookingResource::class;
}
