<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages;

use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\OneOffEventBookingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOneOffEventBooking extends CreateRecord
{
    protected static string $resource = OneOffEventBookingResource::class;
}
