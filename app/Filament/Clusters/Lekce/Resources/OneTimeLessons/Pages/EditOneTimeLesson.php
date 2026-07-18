<?php

namespace App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Pages;

use App\Filament\Clusters\Lekce\Resources\OneTimeLessons\OneTimeLessonResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\PresaleLinkAction;
use App\Filament\Support\Actions\SendOfferInvitationAction;
use App\Filament\Support\Concerns\NotifiesScheduleChange;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOneTimeLesson extends EditRecord
{
    use NotifiesScheduleChange;

    protected static string $resource = OneTimeLessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PresaleLinkAction::make(),
            SendOfferInvitationAction::make(),
            ViewAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function scheduleAttributes(): array
    {
        return ['lesson_date', 'start_time', 'end_time', 'room_id'];
    }
}
