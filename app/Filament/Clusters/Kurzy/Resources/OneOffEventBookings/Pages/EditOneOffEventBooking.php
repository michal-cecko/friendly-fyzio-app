<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages;

use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\OneOffEventBookingResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditOneOffEventBooking extends BaseEditRecord
{
    protected static string $resource = OneOffEventBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
