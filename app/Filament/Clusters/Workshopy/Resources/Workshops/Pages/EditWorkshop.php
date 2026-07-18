<?php

namespace App\Filament\Clusters\Workshopy\Resources\Workshops\Pages;

use App\Filament\Clusters\Workshopy\Resources\Workshops\WorkshopResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\PresaleLinkAction;
use App\Filament\Support\Actions\SendOfferInvitationAction;
use App\Filament\Support\Concerns\NotifiesScheduleChange;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkshop extends EditRecord
{
    use NotifiesScheduleChange;

    protected static string $resource = WorkshopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PresaleLinkAction::make(),
            SendOfferInvitationAction::make(),
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            ActivityLogAction::make(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function scheduleAttributes(): array
    {
        return ['workshop_date', 'start_time', 'end_time', 'room_id'];
    }
}
