<?php

namespace App\Filament\Clusters\Kurzy\Resources\Lessons\Pages;

use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\PresaleLinkAction;
use App\Filament\Support\Actions\SendBulkParticipantEmailAction;
use App\Filament\Support\Actions\SendOfferInvitationAction;
use App\Filament\Support\Concerns\HasCourseBreadcrumbs;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewLesson extends ViewRecord
{
    use HasCourseBreadcrumbs;
    use RendersRelationManagersAsSections;

    protected static string $resource = LessonResource::class;

    public function getTitle(): string
    {
        /** @var Lesson $record */
        $record = $this->getRecord();

        return 'Lekce '.$record->name;
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
                // Who was excused, who was put back — those are logged against
                // the seat they changed, and they are half of what happened to
                // this lesson.
                ActivityLogAction::make(fn (Lesson $record): array => [[
                    'type' => (new LessonAttendance)->getMorphClass(),
                    'ids' => $record->attendances()->pluck('id')->all(),
                ]]),
            ])
                ->label('Další akce')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->button()
                ->color('gray'),
        ];
    }
}
