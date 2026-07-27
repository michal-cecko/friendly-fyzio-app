<?php

namespace App\Filament\Clusters\Kurzy\Resources\Lessons\Pages;

use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateLesson extends BaseCreateRecord
{
    protected static string $resource = LessonResource::class;

    protected static ?string $title = 'Nová lekce';
}
