<?php

namespace App\Filament\Resources\OneTimeLessons\Pages;

use App\Filament\Resources\OneTimeLessons\OneTimeLessonResource;
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
