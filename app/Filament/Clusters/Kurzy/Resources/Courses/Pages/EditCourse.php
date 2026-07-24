<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses\Pages;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;

class EditCourse extends BaseEditRecord
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
