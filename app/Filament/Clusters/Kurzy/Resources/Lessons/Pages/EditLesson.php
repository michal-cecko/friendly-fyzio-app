<?php

namespace App\Filament\Clusters\Kurzy\Resources\Lessons\Pages;

use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\PresaleLinkAction;
use App\Filament\Support\Actions\SendBulkParticipantEmailAction;
use App\Filament\Support\Actions\SendOfferInvitationAction;
use App\Filament\Support\Concerns\HasCourseBreadcrumbs;
use App\Filament\Support\Concerns\PromptsScheduleChangeNotification;
use App\Models\Lesson;
use App\Support\Enrollments\NotifyScheduleChange;
use App\Support\Enrollments\OfferScheduleSnapshot;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;

class EditLesson extends BaseEditRecord
{
    use HasCourseBreadcrumbs;
    use PromptsScheduleChangeNotification;

    protected static string $resource = LessonResource::class;

    public function getTitle(): string
    {
        /** @var Lesson $record */
        $record = $this->getRecord();

        return 'Upravit lekci '.$record->name;
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
                ForceDeleteAction::make(),
                RestoreAction::make(),
                ActivityLogAction::make(),
            ])
                ->label('Další akce')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->button()
                ->color('gray'),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function scheduleAttributes(): array
    {
        return ['lesson_date', 'start_time', 'end_time', 'room_id', 'instructor_id'];
    }

    /**
     * @return array<string, string>
     */
    protected function captureScheduleSnapshot(): array
    {
        return OfferScheduleSnapshot::capture($this->getRecord());
    }

    protected function scheduleChangeAudience(): string
    {
        return 'přihlášené účastníky a lektora';
    }

    /**
     * @param  array<string, string>  $snapshot
     */
    protected function sendScheduleChangeNotification(?string $reason, array $snapshot): int
    {
        return app(NotifyScheduleChange::class)($this->getRecord(), $snapshot, $reason);
    }
}
