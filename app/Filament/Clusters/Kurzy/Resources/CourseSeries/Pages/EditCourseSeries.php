<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Actions\PresaleLinkAction;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCourseSeries extends EditRecord
{
    protected static string $resource = CourseSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PresaleLinkAction::make(),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
