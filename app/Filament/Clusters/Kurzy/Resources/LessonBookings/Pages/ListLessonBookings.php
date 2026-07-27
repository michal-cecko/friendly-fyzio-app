<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages;

use App\Filament\Clusters\Kurzy\Resources\LessonBookings\LessonBookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLessonBookings extends ListRecords
{
    protected static string $resource = LessonBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
