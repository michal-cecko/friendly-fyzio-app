<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessons\Pages;

use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessons\OneTimeLessonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOneTimeLessons extends ListRecords
{
    protected static string $resource = OneTimeLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
