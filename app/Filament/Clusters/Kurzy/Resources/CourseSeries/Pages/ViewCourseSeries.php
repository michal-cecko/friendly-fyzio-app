<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCourseSeries extends ViewRecord
{
    protected static string $resource = CourseSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
