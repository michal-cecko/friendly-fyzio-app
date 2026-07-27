<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\PresaleLinkAction;
use App\Filament\Support\Actions\SendBulkParticipantEmailAction;
use App\Filament\Support\Actions\SendOfferInvitationAction;
use App\Filament\Support\Concerns\HasCourseBreadcrumbs;
use App\Models\CourseSeries;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCourseSeries extends ViewRecord
{
    use HasCourseBreadcrumbs;

    protected static string $resource = CourseSeriesResource::class;

    public function getTitle(): string
    {
        /** @var CourseSeries $record */
        $record = $this->getRecord();

        return 'Série kurzu '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            // The catch-all dropdown always sits last in the header.
            ActionGroup::make([
                SendBulkParticipantEmailAction::make(),
                PresaleLinkAction::make(),
                SendOfferInvitationAction::make(),
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
