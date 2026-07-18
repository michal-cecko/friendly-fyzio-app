<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Actions\PresaleLinkAction;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCourseSeries extends ViewRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = CourseSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PresaleLinkAction::make(),
            EditAction::make(),
        ];
    }
}
