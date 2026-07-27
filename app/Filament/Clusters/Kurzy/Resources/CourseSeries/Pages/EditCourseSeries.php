<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\PresaleLinkAction;
use App\Filament\Support\Actions\SendBulkParticipantEmailAction;
use App\Filament\Support\Actions\SendOfferInvitationAction;
use App\Filament\Support\Concerns\HasCourseBreadcrumbs;
use App\Models\CourseSeries;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;

class EditCourseSeries extends BaseEditRecord
{
    use HasCourseBreadcrumbs;

    protected static string $resource = CourseSeriesResource::class;

    public function getTitle(): string
    {
        /** @var CourseSeries $record */
        $record = $this->getRecord();

        return 'Upravit sérii kurzu '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveHeaderAction(),
            // The catch-all dropdown always sits last in the header.
            ActionGroup::make([
                SendBulkParticipantEmailAction::make(),
                PresaleLinkAction::make(),
                SendOfferInvitationAction::make(),
                ViewAction::make(),
                DeleteAction::make(),
                ActivityLogAction::make(),
            ])
                ->label('Další akce')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->button()
                ->color('gray'),
        ];
    }
}
