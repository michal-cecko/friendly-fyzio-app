<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses\Pages;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCourse extends ViewRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
