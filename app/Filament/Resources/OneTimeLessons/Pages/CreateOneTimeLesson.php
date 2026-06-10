<?php

namespace App\Filament\Resources\OneTimeLessons\Pages;

use App\Filament\Resources\OneTimeLessons\OneTimeLessonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOneTimeLesson extends CreateRecord
{
    protected static string $resource = OneTimeLessonResource::class;
}
