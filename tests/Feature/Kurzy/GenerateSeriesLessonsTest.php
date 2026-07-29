<?php

namespace Tests\Feature\Kurzy;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\DayOfWeek;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\CreateCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\RelationManagers\LessonsRelationManager;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\Room;
use App\Models\User;
use App\Support\Lessons\LessonScheduleGenerator;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class GenerateSeriesLessonsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * A série running Mondays and Wednesdays over four calendar weeks:
     * 2026-08-03 (Mon) … 2026-08-28 (Fri) → 4 Mondays + 4 Wednesdays.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function series(array $attributes = []): CourseSeries
    {
        $course = Course::factory()->create([
            'instructor_id' => User::factory()->lecturer()->create()->getKey(),
        ]);

        return CourseSeries::factory()
            ->for($course)
            ->create([
                'start_date' => '2026-08-03',
                'end_date' => '2026-08-28',
                'days_of_week' => [DayOfWeek::Monday->value, DayOfWeek::Wednesday->value],
                'start_time' => '17:00:00',
                'end_time' => '18:00:00',
                'room_id' => Room::factory()->create()->getKey(),
                'capacity' => 10,
                'status' => CourseSeriesStatus::Open,
                ...$attributes,
            ]);
    }

    protected function generator(): LessonScheduleGenerator
    {
        return app(LessonScheduleGenerator::class);
    }

    public function test_it_creates_a_lesson_for_every_scheduled_weekday(): void
    {
        $series = $this->series();

        $result = $this->generator()->generate($series);

        $this->assertSame(8, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertFalse($result['capped']);

        $dates = $series->lessons()->pluck('lesson_date')
            ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())
            ->all();

        $this->assertSame([
            '2026-08-03', '2026-08-05', '2026-08-10', '2026-08-12',
            '2026-08-17', '2026-08-19', '2026-08-24', '2026-08-26',
        ], $dates);

        $lesson = $series->lessons()->first();
        $this->assertSame('17:00:00', $lesson->start_time);
        $this->assertSame('18:00:00', $lesson->end_time);
        $this->assertSame($series->room_id, $lesson->room_id);
        $this->assertSame($series->leadInstructor()->getKey(), $lesson->instructor_id);
    }

    public function test_the_range_is_inclusive_on_both_ends(): void
    {
        // 2026-08-03 and 2026-08-31 are both Mondays and both boundaries.
        $series = $this->series([
            'end_date' => '2026-08-31',
            'days_of_week' => [DayOfWeek::Monday->value],
        ]);

        $this->generator()->generate($series);

        $dates = $series->lessons()->pluck('lesson_date')
            ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())
            ->all();

        $this->assertSame(['2026-08-03', '2026-08-10', '2026-08-17', '2026-08-24', '2026-08-31'], $dates);
    }

    public function test_a_second_run_creates_nothing(): void
    {
        $series = $this->series();

        $this->generator()->generate($series);
        $result = $this->generator()->generate($series->fresh());

        $this->assertSame(0, $result['created']);
        $this->assertSame(8, $result['skipped']);
        $this->assertSame(8, Lesson::query()->where('series_id', $series->getKey())->count());
    }

    public function test_a_deleted_lesson_is_not_recreated(): void
    {
        $series = $this->series();
        $this->generator()->generate($series);

        $series->lessons()->whereDate('lesson_date', '2026-08-12')->first()->delete();

        $result = $this->generator()->generate($series->fresh());

        $this->assertSame(0, $result['created']);
        $this->assertSame(8, $result['skipped']);
        $this->assertSame(7, $series->lessons()->count());
        $this->assertDatabaseCount('lessons', 8);
    }

    public function test_a_lesson_at_another_time_still_occupies_its_date(): void
    {
        $series = $this->series();

        Lesson::factory()->for($series, 'series')->create([
            'lesson_date' => '2026-08-05',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $result = $this->generator()->generate($series);

        $this->assertSame(7, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $series->lessons()->whereDate('lesson_date', '2026-08-05')->count());
    }

    public function test_generated_lessons_get_their_attendance_roster(): void
    {
        $series = $this->series();

        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'client_id' => User::factory()->customer()->create()->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        $this->generator()->generate($series);

        // LessonObserver builds the roster on create() — a bulk insert would skip it.
        $this->assertSame(8, LessonAttendance::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->count());
    }

    public function test_an_incomplete_schedule_generates_nothing(): void
    {
        foreach ([
            ['days_of_week' => []],
            ['days_of_week' => null],
            ['start_time' => null],
            ['end_time' => null],
            ['room_id' => null],
        ] as $missing) {
            $series = $this->series($missing);

            $result = $this->generator()->generate($series);

            $this->assertSame(0, $result['created'], 'Expected no lessons for '.json_encode($missing));
            $this->assertFalse($series->hasLessonSchedule());
            $this->assertSame(0, $series->lessons()->count());
        }
    }

    public function test_it_stops_at_the_maximum_and_reports_it(): void
    {
        $series = $this->series([
            'start_date' => '2026-08-03',
            // Well over MAX_LESSONS worth of Mondays + Wednesdays.
            'end_date' => '2030-08-03',
        ]);

        $result = $this->generator()->generate($series);

        $this->assertSame(LessonScheduleGenerator::MAX_LESSONS, $result['created']);
        $this->assertTrue($result['capped']);
        $this->assertSame(LessonScheduleGenerator::MAX_LESSONS, $series->lessons()->count());
    }

    public function test_occurrence_dates_are_ascending_and_deduplicated(): void
    {
        $dates = $this->generator()->occurrenceDates(
            [DayOfWeek::Wednesday, DayOfWeek::Monday, DayOfWeek::Monday],
            CarbonImmutable::parse('2026-08-03'),
            CarbonImmutable::parse('2026-08-12'),
        );

        $this->assertSame(
            ['2026-08-03', '2026-08-05', '2026-08-10', '2026-08-12'],
            array_map(fn (CarbonImmutable $date): string => $date->toDateString(), $dates),
        );
    }

    public function test_the_relation_manager_action_generates_the_lessons(): void
    {
        $series = $this->series();

        Livewire::test(LessonsRelationManager::class, [
            'ownerRecord' => $series,
            'pageClass' => ViewCourseSeries::class,
        ])
            ->callAction(TestAction::make('generateSeriesLessons')->table())
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame(8, $series->lessons()->count());
    }

    public function test_the_relation_manager_action_is_disabled_without_a_schedule(): void
    {
        $series = $this->series(['days_of_week' => null]);

        Livewire::test(LessonsRelationManager::class, [
            'ownerRecord' => $series,
            'pageClass' => ViewCourseSeries::class,
        ])
            ->assertActionDisabled(TestAction::make('generateSeriesLessons')->table());
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    protected function createSeriesThroughForm(string $name, array $schedule): void
    {
        $course = Course::factory()->create([
            'instructor_id' => User::factory()->lecturer()->create()->getKey(),
        ]);

        Livewire::test(CreateCourseSeries::class)
            ->fillForm([
                'course_id' => $course->getKey(),
                'name' => $name,
                'start_date' => '2026-08-03',
                'end_date' => '2026-08-28',
                'price' => 2400,
                'capacity' => 10,
                'max_substitutions' => 2,
                ...$schedule,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect($this->expectedRedirect($name));
    }

    protected function expectedRedirect(string $name): string
    {
        $series = CourseSeries::query()->where('name', $name)->sole();

        return $series->hasLessonSchedule()
            ? CourseSeriesResource::getUrl('view', [
                'record' => $series,
                CreateCourseSeries::PROMPT_PARAM => 1,
            ])
            : CourseSeriesResource::getUrl('view', ['record' => $series]);
    }

    public function test_creating_a_serie_with_a_schedule_redirects_with_the_prompt_flag(): void
    {
        $this->createSeriesThroughForm('Podzim 2026', [
            'days_of_week' => [DayOfWeek::Monday->value, DayOfWeek::Wednesday->value],
            'start_time' => '17:00',
            'end_time' => '18:00',
            'room_id' => Room::factory()->create()->getKey(),
        ]);

        $series = CourseSeries::query()->where('name', 'Podzim 2026')->sole();

        $this->assertTrue($series->hasLessonSchedule());
        // Nothing is created until the prompt on the detail page is confirmed.
        $this->assertSame(0, $series->lessons()->count());
    }

    public function test_the_detail_page_prompts_and_generates_when_confirmed(): void
    {
        $series = $this->series();

        Livewire::withQueryParams([CreateCourseSeries::PROMPT_PARAM => 1])
            ->test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->assertActionMounted('generateSeriesLessons')
            ->callMountedAction()
            ->assertNotified();

        $this->assertSame(8, $series->lessons()->count());
    }

    public function test_the_detail_page_does_not_prompt_without_the_flag(): void
    {
        $series = $this->series();

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->assertActionNotMounted('generateSeriesLessons');

        $this->assertSame(0, $series->lessons()->count());
    }

    public function test_creating_a_serie_without_a_schedule_prompts_nothing(): void
    {
        $this->createSeriesThroughForm('Bez rozvrhu', []);

        $series = CourseSeries::query()->where('name', 'Bez rozvrhu')->sole();

        $this->assertFalse($series->hasLessonSchedule());

        Livewire::withQueryParams([CreateCourseSeries::PROMPT_PARAM => 1])
            ->test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->assertActionNotMounted('generateSeriesLessons');
    }

    public function test_the_schedule_label_reads_as_one_line(): void
    {
        $series = $this->series();
        $series->setRelation('room', $series->room()->first());

        $this->assertSame(
            'Pondělí, Středa · 17:00–18:00 · '.$series->room->name,
            $series->scheduleLabel(),
        );

        $this->assertNull($this->series(['room_id' => null])->scheduleLabel());
    }
}
