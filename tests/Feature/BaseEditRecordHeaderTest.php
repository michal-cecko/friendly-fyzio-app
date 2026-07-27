<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages\EditCourseCategory;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\EditCourse;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\EditCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\EditLesson;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Behaviour that {@see BaseEditRecord} adds to
 * every edit page: a header Save button reachable above a long form, and the
 * clearer "Zobrazit detail" label on the record view action — whether that
 * action sits directly in the header or inside a "Další akce" dropdown.
 */
class BaseEditRecordHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_plain_edit_page_gets_a_header_save_button(): void
    {
        $course = Course::factory()->create();

        Livewire::test(EditCourse::class, ['record' => $course->getKey()])
            ->assertActionExists('saveHeader');
    }

    public function test_header_save_button_persists_changes(): void
    {
        $course = Course::factory()->create(['name' => 'Původní název']);

        Livewire::test(EditCourse::class, ['record' => $course->getKey()])
            ->fillForm(['name' => 'Nový název'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nový název', $course->refresh()->name);
    }

    public function test_top_level_view_action_is_relabelled(): void
    {
        $lesson = Lesson::factory()->create();

        Livewire::test(EditLesson::class, ['record' => $lesson->getKey()])
            ->assertActionHasLabel('view', 'Zobrazit detail');
    }

    public function test_grouped_view_action_is_relabelled_and_save_is_not_duplicated(): void
    {
        $category = CourseCategory::factory()->create();

        Livewire::test(EditCourseCategory::class, ['record' => $category->getKey()])
            ->assertActionExists('saveHeader')
            ->assertActionHasLabel('view', 'Zobrazit detail');
    }

    public function test_course_edit_keeps_only_save_in_the_header(): void
    {
        $course = Course::factory()->create();

        Livewire::test(EditCourse::class, ['record' => $course->getKey()])
            ->assertActionExists('saveHeader')
            ->assertActionExists('view')
            ->assertActionExists('delete')
            ->assertActionHasLabel('view', 'Zobrazit detail');

        $headerActions = Livewire::test(EditCourse::class, ['record' => $course->getKey()])
            ->instance()
            ->getCachedHeaderActions();

        $this->assertCount(2, $headerActions);
        $this->assertInstanceOf(Action::class, $headerActions[0]);
        $this->assertSame('saveHeader', $headerActions[0]->getName());
        $this->assertInstanceOf(ActionGroup::class, $headerActions[1]);
    }

    public function test_course_series_edit_keeps_only_save_in_the_header(): void
    {
        $series = CourseSeries::factory()->create();

        $headerActions = Livewire::test(EditCourseSeries::class, ['record' => $series->getKey()])
            ->assertActionExists('saveHeader')
            ->assertActionHasLabel('view', 'Zobrazit detail')
            ->instance()
            ->getCachedHeaderActions();

        $this->assertCount(2, $headerActions);
        $this->assertInstanceOf(Action::class, $headerActions[0]);
        $this->assertSame('saveHeader', $headerActions[0]->getName());
        $this->assertInstanceOf(ActionGroup::class, $headerActions[1]);
        $this->assertSame('Další akce', $headerActions[1]->getLabel());

        $this->assertSame(
            ['emailParticipants', 'presaleLink', 'sendInvitation', 'view', 'delete', 'activityLog'],
            array_map(
                fn (Action $action): string => $action->getName(),
                array_values($headerActions[1]->getFlatActions()),
            ),
        );
    }
}
