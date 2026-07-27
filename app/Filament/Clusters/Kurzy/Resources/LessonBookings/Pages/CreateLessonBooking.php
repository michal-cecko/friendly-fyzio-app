<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages;

use App\Filament\Clusters\Kurzy\Resources\LessonBookings\LessonBookingResource;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateLessonBooking extends BaseCreateRecord
{
    protected static string $resource = LessonBookingResource::class;

    protected static ?string $title = 'Nová přihláška na akci';
}
