<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\PresaleLinkAction;
use App\Filament\Support\Actions\SendOfferInvitationAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditCourseSeries extends BaseEditRecord
{
    protected static string $resource = CourseSeriesResource::class;

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
}
