<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourseSeries extends ListRecords
{
    protected static string $resource = CourseSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
