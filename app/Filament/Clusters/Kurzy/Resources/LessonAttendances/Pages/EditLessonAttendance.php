<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages;

use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\LessonAttendanceResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\LessonAttendance;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditLessonAttendance extends BaseEditRecord
{
    protected static string $resource = LessonAttendanceResource::class;

    public function getTitle(): string
    {
        /** @var LessonAttendance $record */
        $record = $this->getRecord();

        return 'Upravit docházku '.($record->enrollment?->client?->name ?? 'bez klienta');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
