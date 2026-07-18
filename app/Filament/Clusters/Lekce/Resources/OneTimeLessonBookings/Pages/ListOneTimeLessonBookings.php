<?php

namespace App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\Pages;

use App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\OneTimeLessonBookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOneTimeLessonBookings extends ListRecords
{
    protected static string $resource = OneTimeLessonBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
