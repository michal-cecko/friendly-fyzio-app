<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages;

use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Models\CourseSeries;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creating a série with a rozvrh filled in is immediately followed by the offer
 * to materialize it — the lessons are what the série is for, and asking once
 * saves a dozen manual "Přidat lekci" forms.
 *
 * The prompt itself lives on {@see ViewCourseSeries}, not here. A create page
 * cannot hold a modal open: {@see CreateRecord::getRedirectUrl()} is declared
 * non-nullable and its redirect always fires, which would drop anything mounted
 * in afterCreate() — the trick {@see App\Filament\Support\Concerns\PromptsScheduleChangeNotification}
 * uses on Edit pages does not transfer. So the save redirects to the série's
 * detail page carrying {@see self::PROMPT_PARAM}, and that page opens the modal.
 * Staff end up where the lessons actually appear.
 *
 * A série saved without a rozvrh redirects normally — there would be nothing to generate.
 */
class CreateCourseSeries extends BaseCreateRecord
{
    protected static string $resource = CourseSeriesResource::class;

    protected static ?string $title = 'Nová série kurzu';

    /**
     * Query flag telling the detail page to ask about generating the lessons.
     */
    public const PROMPT_PARAM = 'vygenerovat-lekce';

    protected function getRedirectUrl(): string
    {
        /** @var CourseSeries $record */
        $record = $this->getRecord();

        if (! $record->hasLessonSchedule()) {
            return parent::getRedirectUrl();
        }

        return static::getResource()::getUrl('view', [
            'record' => $record,
            self::PROMPT_PARAM => 1,
        ]);
    }
}
