<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessons\Pages;

use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessons\OneTimeLessonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOneTimeLesson extends CreateRecord
{
    protected static string $resource = OneTimeLessonResource::class;
}
