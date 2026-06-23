<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessons\Pages;

use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessons\OneTimeLessonResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOneTimeLesson extends ViewRecord
{
    protected static string $resource = OneTimeLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
