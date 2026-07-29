<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\GenerateSeriesLessonsAction;
use App\Filament\Support\Actions\PresaleLinkAction;
use App\Filament\Support\Actions\SendBulkParticipantEmailAction;
use App\Filament\Support\Actions\SendOfferInvitationAction;
use App\Filament\Support\Concerns\HasCourseBreadcrumbs;
use App\Models\CourseSeries;
use Filament\Actions\Action;
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

    /**
     * Opens the generate-lessons prompt when we arrived straight from creating a
     * série with a rozvrh — see {@see CreateCourseSeries} for why the modal is
     * hosted here rather than on the create page.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var CourseSeries $series */
        $series = $this->getRecord();

        if (request()->boolean(CreateCourseSeries::PROMPT_PARAM) && $series->hasLessonSchedule()) {
            $this->mountAction('generateSeriesLessons');
        }
    }

    /**
     * Never rendered as a header button — the série's Lekce tab already carries
     * the same action. This declaration exists so the post-create prompt has
     * something to mount.
     */
    public function generateSeriesLessonsAction(): Action
    {
        return GenerateSeriesLessonsAction::make()
            ->record($this->getRecord())
            ->modalHeading('Vygenerovat lekce k nové sérii?')
            ->modalCancelActionLabel('Teď ne');
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
