<?php

namespace App\Filament\Resources\OneTimeLessons\Pages;

use App\Filament\Resources\OneTimeLessons\OneTimeLessonResource;
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
