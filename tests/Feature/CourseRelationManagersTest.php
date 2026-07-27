<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\EditCourse;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\ViewCourse;
use App\Filament\Clusters\Kurzy\Resources\Courses\RelationManagers\SeriesRelationManager;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\EditCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\RelationManagers\LessonsRelationManager;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\RelationManagers\SubstituteRulesRelationManager;
use App\Filament\Support\RelationManagers\CourseSeriesEnrollmentsRelationManager;
use App\Filament\Support\RelationManagers\WaitlistEntriesRelationManager;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\SubstituteRule;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourseRelationManagersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_course_view_lists_its_series(): void
    {
        $course = Course::factory()->create();
        $series = CourseSeries::factory()->count(2)->create(['course_id' => $course->id]);
        $otherSeries = CourseSeries::factory()->create();

        Livewire::test(SeriesRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => ViewCourse::class,
        ])
            ->assertCanSeeTableRecords($series)
            ->assertCanNotSeeTableRecords([$otherSeries]);
    }

    /**
     * A row click belongs on the série's own page — its lessons and enrollments
     * live there — rather than dropping straight into the edit form.
     */
    public function test_clicking_a_series_row_opens_its_detail_page(): void
    {
        $course = Course::factory()->create();
        $series = CourseSeries::factory()->create(['course_id' => $course->id]);

        Livewire::test(SeriesRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => ViewCourse::class,
        ])
            ->assertSee(CourseSeriesResource::getUrl('view', ['record' => $series]), false)
            ->assertDontSee(CourseSeriesResource::getUrl('edit', ['record' => $series]), false);
    }

    public function test_series_can_be_created_inline_for_the_owning_course(): void
    {
        $course = Course::factory()->create();

        Livewire::test(SeriesRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => ViewCourse::class,
        ])
            ->callAction(TestAction::make('create')->table(), [
                'name' => 'Podzimní série 2026',
                'start_date' => '2026-09-01',
                'end_date' => '2026-11-30',
                'capacity' => 10,
                'price' => 2000,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas(CourseSeries::class, [
            'course_id' => $course->id,
            'name' => 'Podzimní série 2026',
        ]);
    }

    public function test_series_view_lists_its_lessons(): void
    {
        $series = CourseSeries::factory()->create();
        $lessons = Lesson::factory()->count(3)->create(['series_id' => $series->id]);
        $otherLesson = Lesson::factory()->create();

        Livewire::test(LessonsRelationManager::class, [
            'ownerRecord' => $series,
            'pageClass' => ViewCourseSeries::class,
        ])
            ->assertCanSeeTableRecords($lessons)
            ->assertCanNotSeeTableRecords([$otherLesson]);
    }

    public function test_series_lesson_times_are_shown_without_seconds(): void
    {
        $series = CourseSeries::factory()->create();
        $lesson = Lesson::factory()->create([
            'series_id' => $series->id,
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
        ]);

        Livewire::test(LessonsRelationManager::class, [
            'ownerRecord' => $series,
            'pageClass' => ViewCourseSeries::class,
        ])
            ->assertCanSeeTableRecords([$lesson])
            ->assertTableColumnFormattedStateSet('start_time', '09:00', $lesson)
            ->assertTableColumnFormattedStateSet('end_time', '10:30', $lesson);
    }

    public function test_lesson_can_be_created_inline_for_the_owning_series(): void
    {
        $series = CourseSeries::factory()->create();
        $instructor = User::factory()->lecturer()->create();
        $room = Room::factory()->create();

        Livewire::test(LessonsRelationManager::class, [
            'ownerRecord' => $series,
            'pageClass' => ViewCourseSeries::class,
        ])
            ->callAction(TestAction::make('create')->table(), [
                'instructor_id' => $instructor->id,
                'room_id' => $room->id,
                'lesson_date' => '2026-09-07',
                'start_time' => '09:00',
                'end_time' => '10:00',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas(Lesson::class, [
            'series_id' => $series->id,
            'instructor_id' => $instructor->id,
            'lesson_date' => '2026-09-07 00:00:00',
        ]);
    }

    public function test_substitute_rule_can_be_created_inline_for_the_owning_series(): void
    {
        $series = CourseSeries::factory()->create();
        $target = CourseSeries::factory()->create();

        Livewire::test(SubstituteRulesRelationManager::class, [
            'ownerRecord' => $series,
            'pageClass' => ViewCourseSeries::class,
        ])
            ->callAction(TestAction::make('create')->table(), [
                'target_series_id' => $target->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas(SubstituteRule::class, [
            'source_series_id' => $series->id,
            'target_series_id' => $target->id,
        ]);
    }

    /**
     * Kurzy and their série carry several relation managers each, so their detail
     * and edit pages keep them behind Filament's tab strip rather than stacking
     * them as sections down the page.
     *
     * @return array<string, array{class-string<Page>, bool}>
     */
    public static function relationManagerPagesProvider(): array
    {
        return [
            'course detail' => [ViewCourse::class, false],
            'course edit' => [EditCourse::class, false],
            'series detail' => [ViewCourseSeries::class, true],
            'series edit' => [EditCourseSeries::class, true],
        ];
    }

    #[DataProvider('relationManagerPagesProvider')]
    public function test_relation_managers_render_as_tabs(string $page, bool $onSeries): void
    {
        $course = Course::factory()->create();
        $series = CourseSeries::factory()->create(['course_id' => $course->id]);

        $record = $onSeries ? $series : $course;

        $content = Livewire::test($page, ['record' => $record->getKey()])
            ->instance()
            ->getRelationManagersContentComponent();

        $this->assertInstanceOf(Tabs::class, $content);
    }

    public function test_waitlist_tab_is_relabelled_on_a_course_but_not_on_a_series(): void
    {
        $course = Course::factory()->create();
        $series = CourseSeries::factory()->create();

        $this->assertSame('Chci vědět první', WaitlistEntriesRelationManager::getTitle($course, ViewCourse::class));
        $this->assertSame('Čekací listina', WaitlistEntriesRelationManager::getTitle($series, ViewCourseSeries::class));
    }

    /**
     * The tab holds sign-ups (the records), not the act of signing in — hence
     * "Přihlášky" rather than the easily-confused "Přihlášení".
     */
    public function test_series_enrollments_tab_is_titled_prihlasky(): void
    {
        $series = CourseSeries::factory()->create();

        $this->assertSame(
            'Přihlášky',
            CourseSeriesEnrollmentsRelationManager::getTitle($series, ViewCourseSeries::class),
        );
    }
}
