<?php

namespace App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Pages;

use App\Filament\Clusters\Lekce\Resources\OneTimeLessons\OneTimeLessonResource;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOneTimeLesson extends ViewRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = OneTimeLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
