<?php

namespace App\Filament\Support\Actions;

use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Models\CourseSeries;
use App\Support\Lessons\LessonScheduleGenerator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Materializes a série's rozvrh into lessons — every scheduled date between its
 * start and end that has none yet.
 *
 * The run is additive and repeatable: dates that already carry a lesson are
 * skipped, including ones whose lesson was deleted, so a cancelled session is
 * never resurrected. That makes the button safe to press at any point — after
 * creating the série, after extending its end date, or after adding a few
 * lessons by hand.
 *
 * Used both from the série's Lekce tab and from the prompt that follows creating
 * a série ({@see App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries}).
 * Both hosts re-resolve their record every request, so the bound record survives
 * the modal's extra Livewire round-trip.
 */
class GenerateSeriesLessonsAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generateSeriesLessons';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Vygenerovat lekce')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            // Custom action, so it carries no implicit policy check.
            ->visible(function (): bool {
                $series = $this->resolveSeries();

                return $series !== null && CourseSeriesResource::canEdit($series);
            })
            // Shown but inert while the rozvrh is missing, so the feature stays
            // discoverable instead of silently absent.
            ->disabled(fn (): bool => ! $this->resolveSeries()?->hasLessonSchedule())
            ->tooltip(fn (): ?string => $this->resolveSeries()?->hasLessonSchedule()
                ? null
                : 'Nejdřív vyplňte rozvrh v nastavení série.')
            ->modalHeading('Vygenerovat lekce')
            ->modalIcon(Heroicon::OutlinedCalendarDays)
            ->modalDescription(fn (): string => $this->describe($this->resolveSeries()))
            ->schema(fn (): array => [
                Text::make($this->rules($this->resolveSeries()))
                    ->color('gray'),
            ])
            ->modalSubmitActionLabel('Vygenerovat')
            ->modalCancelActionLabel('Zpět')
            ->action(function (): void {
                $series = $this->resolveSeries();

                if ($series === null) {
                    return;
                }

                $result = app(LessonScheduleGenerator::class)->generate($series);

                if ($result['created'] === 0) {
                    Notification::make()
                        ->warning()
                        ->title('Nevytvořena žádná lekce.')
                        ->body($result['skipped'] > 0
                            ? 'Všechny termíny z rozvrhu už lekci mají.'
                            : 'Z rozvrhu nevychází mezi začátkem a koncem série žádný termín.')
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title("Vytvořeno lekcí: {$result['created']}")
                    ->body($this->summary($result))
                    ->send();
            });
    }

    /**
     * The série this run works on: the bound record, or the key handed over as a
     * mount argument when the action is opened without one.
     */
    protected function resolveSeries(): ?CourseSeries
    {
        $record = $this->getRecord();

        return $record instanceof CourseSeries ? $record : null;
    }

    /**
     * What pressing the button will do, in numbers, so nothing is a surprise.
     */
    protected function describe(?CourseSeries $series): string
    {
        if ($series === null || ! $series->hasLessonSchedule()) {
            return 'Série zatím nemá rozvrh. Doplňte v jejím nastavení na záložce Rozvrh dny a časy konání — a u každého termínu i místnost.';
        }

        $generator = app(LessonScheduleGenerator::class);
        $planned = count($generator->plannedSessions($series));
        $missing = count($generator->missingSessions($series));

        $intro = 'Podle rozvrhu ('.$series->scheduleLabel().') vychází mezi '
            .$series->start_date->format('j. n. Y').' a '.$series->end_date->format('j. n. Y')
            .' celkem '.$planned.' termínů.';

        if ($missing === 0) {
            return $intro.' Všechny už lekci mají, takže se nic nevytvoří.';
        }

        $existing = $planned - $missing;

        return $intro.' '.($existing > 0
            ? "Existujících lekcí: {$existing}, vytvoří se {$missing}."
            : "Vytvoří se {$missing} lekcí.")
            .' Existující lekce zůstanou beze změny — smazané se neobnovují.';
    }

    /**
     * The rules of the run, spelled out in the modal. Generating touches the
     * schedule of people who are already signed up, so what it will and won't do
     * belongs in front of staff at the moment they press the button — not only in
     * the nápověda.
     */
    protected function rules(?CourseSeries $series): HtmlString
    {
        $points = [
            'Lekce se zakládají jen na dnech z rozvrhu, které padnou <strong>mezi začátek a konec série</strong>. Když chcete další, posuňte nejdřív konec série.',
            'Termín, který už lekci má, se <strong>přeskočí</strong> — i když ji někdo posunul na jiný čas nebo do jiné místnosti. Nic existujícího se nepřepisuje.',
            'Má-li série v jeden den <strong>dva různé časy</strong> (ranní a večerní skupina), vygenerují se oba — u takového dne se hlídá i čas.',
            '<strong>Smazané lekce se neobnovují.</strong> Když jste lekci zrušili (třeba kvůli svátku), zůstane zrušená i po dalším spuštění.',
            'Spouštět to jde <strong>opakovaně</strong> — pokaždé se doplní jen to, co chybí.',
            'Vygenerované lekce dostanou lektora ze série a <strong>místnost z toho řádku rozvrhu</strong>, na který termín padne. U jednotlivé lekce se to pak dá změnit.',
        ];

        if ($series !== null && $series->activeTakers()->exists()) {
            $points[] = 'Série už má přihlášené — nové lekce se jim rovnou objeví v docházce. '
                .'Pozor také na to, že <strong>cena pro nově příchozí</strong> se počítá podle poměru zbývajících lekcí, takže se generováním změní.';
        }

        return new HtmlString(
            '<p class="mb-2">Co se stane:</p><ul class="list-disc ps-5 space-y-1"><li>'
            .implode('</li><li>', $points)
            .'</li></ul>'
        );
    }

    /**
     * @param  array{created: int, skipped: int, capped: bool}  $result
     */
    protected function summary(array $result): string
    {
        $parts = [];

        if ($result['skipped'] > 0) {
            $parts[] = "Přeskočeno už existujících termínů: {$result['skipped']}.";
        }

        if ($result['capped']) {
            $parts[] = 'Najednou se vytvoří nejvýš '.LessonScheduleGenerator::MAX_LESSONS
                .' lekcí — zbytek dogenerujete dalším spuštěním. Zkontrolujte, jestli má série správný konec.';
        }

        return implode(' ', $parts);
    }
}
