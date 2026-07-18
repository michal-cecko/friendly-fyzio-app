<?php

namespace App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Pages;

use App\Filament\Clusters\Lekce\Resources\OneTimeLessons\OneTimeLessonResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOneTimeLesson extends EditRecord
{
    protected static string $resource = OneTimeLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
