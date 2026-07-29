<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\EditLesson;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\ListLessons;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\EventCategory;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\User;
use App\Support\Lessons\ReleaseFreeSpots;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LessonResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_can_list_records(): void
    {
        $records = Lesson::factory()->count(3)->create();

        Livewire::test(ListLessons::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        $category = EventCategory::factory()->create();
        $instructor = User::factory()->lecturer()->create();
        $room = Room::factory()->create();

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'event_category_id' => $category->id,
                'name' => 'Workshop dýchání',
                'slug' => 'workshop-dychani',
                'instructor_id' => $instructor->id,
                'room_id' => $room->id,
                'lesson_date' => '2026-03-15',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'capacity' => 15,
                'price' => 800,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Lesson::class, [
            'name' => 'Workshop dýchání',
            'slug' => 'workshop-dychani',
            'event_category_id' => $category->id,
            'instructor_id' => $instructor->id,
        ]);
    }

    public function test_admin_can_create_course_linked_record(): void
    {
        $category = EventCategory::factory()->create();
        $course = Course::factory()->create();
        $instructor = User::factory()->lecturer()->create();
        $room = Room::factory()->create();

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'event_category_id' => $category->id,
                'course_id' => $course->id,
                'name' => 'Ochutnávková lekce',
                'slug' => 'ochutnavkova-lekce',
                'instructor_id' => $instructor->id,
                'room_id' => $room->id,
                'lesson_date' => '2026-03-20',
                'start_time' => '14:00',
                'end_time' => '15:00',
                'capacity' => 8,
                'price' => 500,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Lesson::class, [
            'slug' => 'ochutnavkova-lekce',
            'course_id' => $course->id,
            'lesson_date' => '2026-03-20 00:00:00',
        ]);
    }

    public function test_the_self_cancel_window_is_saved_on_the_lesson_and_otherwise_inherited(): void
    {
        $category = EventCategory::factory()->create(['cancel_before_hours' => 168]);
        $instructor = User::factory()->lecturer()->create();
        $room = Room::factory()->create();

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'event_category_id' => $category->id,
                'name' => 'Náročný workshop',
                'slug' => 'narocny-workshop',
                'instructor_id' => $instructor->id,
                'room_id' => $room->id,
                'lesson_date' => '2026-03-15',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'capacity' => 15,
                'price' => 800,
                'cancel_before_hours' => 240,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $lesson = Lesson::query()->where('slug', 'narocny-workshop')->sole();

        $this->assertSame(240, $lesson->cancelBeforeHours());

        // Cleared, it drops back to the category — not to a frozen copy of 240.
        $lesson->update(['cancel_before_hours' => null]);

        $this->assertSame(168, $lesson->fresh()->cancelBeforeHours());
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateLesson::class)
            ->fillForm([
                'event_category_id' => null,
                'name' => null,
                'slug' => null,
                'instructor_id' => null,
                'lesson_date' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'event_category_id' => 'required',
                'name' => 'required',
                'slug' => 'required',
                'instructor_id' => 'required',
                'lesson_date' => 'required',
            ]);
    }

    public function test_admin_can_edit_record(): void
    {
        $record = Lesson::factory()->create();

        Livewire::test(EditLesson::class, ['record' => $record->getKey()])
            ->fillForm([
                'name' => 'Aktualizovaná akce',
                'lesson_date' => '2026-04-25',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Lesson::class, [
            'id' => $record->id,
            'name' => 'Aktualizovaná akce',
            'lesson_date' => '2026-04-25 00:00:00',
        ]);
    }

    /**
     * The session details and what makes the lesson sellable are two tabs, not
     * two stacked sections.
     */
    public function test_edit_form_splits_the_two_sections_into_tabs(): void
    {
        $record = Lesson::factory()->create();

        $tabs = Livewire::test(EditLesson::class, ['record' => $record->getKey()])
            ->instance()
            ->getSchema('form')
            ->getComponents(withHidden: true);

        $tabs = collect($tabs)->first(fn ($component): bool => $component instanceof Tabs);

        $this->assertNotNull($tabs, 'The lesson form has to be laid out as tabs.');
        $this->assertSame(
            ['Termín', 'Veřejný prodej'],
            collect($tabs->getChildSchema()->getComponents(withHidden: true))
                ->map(fn (Tab $tab): string => $tab->getLabel())
                ->all(),
        );
    }

    /**
     * The instructor picker restricts its options to lecturers, but a lesson may
     * already be assigned to someone who isn't one (e.g. a therapist from a
     * historical import). The selected label must still resolve to the name
     * rather than falling back to the raw UUID.
     */
    public function test_edit_shows_instructor_name_even_when_not_a_lecturer(): void
    {
        $therapist = User::factory()->therapist()->create(['name' => 'Mgr. Anna Kovaříková']);
        $record = Lesson::factory()->create(['instructor_id' => $therapist->id]);

        Livewire::test(EditLesson::class, ['record' => $record->getKey()])
            ->assertSee('Mgr. Anna Kovaříková');
    }

    /**
     * A lesson of a série is reached through its course, so the trail names the
     * course and the série instead of stopping at the flat lesson list.
     */
    public function test_series_lesson_breadcrumbs_lead_through_its_course(): void
    {
        $course = Course::factory()->create(['name' => 'Zdravá záda']);
        $series = CourseSeries::factory()->for($course, 'course')->create(['name' => 'Podzim 2026']);
        $record = Lesson::factory()->for($series, 'series')->create();

        $breadcrumbs = Livewire::test(EditLesson::class, ['record' => $record->getKey()])
            ->instance()
            ->getBreadcrumbs();

        $this->assertContains('Zdravá záda', $breadcrumbs);
        $this->assertContains('Podzim 2026', $breadcrumbs);
        $this->assertNotContains(LessonResource::getUrl(), array_keys($breadcrumbs));

        $courseIndex = array_search('Zdravá záda', array_values($breadcrumbs), strict: true);
        $seriesIndex = array_search('Podzim 2026', array_values($breadcrumbs), strict: true);
        $this->assertLessThan($seriesIndex, $courseIndex, 'The course has to precede its série.');
    }

    /**
     * The two shapes of a lesson need opposite instructions on the sale tab: a
     * standalone one has to be filled in and published by hand, a lesson of a
     * série is filled in and published by {@see ReleaseFreeSpots}
     * on its own.
     */
    public function test_sale_tab_explains_manual_publishing_for_a_standalone_lesson(): void
    {
        $record = Lesson::factory()->create(['series_id' => null]);

        Livewire::test(EditLesson::class, ['record' => $record->getKey()])
            ->assertSee('Samostatná lekce se prodává sama za sebe.')
            ->assertDontSee('Tady nemusíte vyplňovat nic.');
    }

    public function test_sale_tab_explains_the_automatic_release_for_a_lesson_of_a_series(): void
    {
        $course = Course::factory()->create(['drop_in_price' => 250]);
        $series = CourseSeries::factory()->for($course, 'course')->create();
        $record = Lesson::factory()->for($series, 'series')->create();

        Livewire::test(EditLesson::class, ['record' => $record->getKey()])
            ->assertSee('Tady nemusíte vyplňovat nic.')
            ->assertSee('nejdřív dostane přednost čekací listina série')
            // The course prices single seats, so the dead-end warning stays away
            // and the price field names the amount that would be inherited.
            ->assertDontSee('nemá vyplněnou')
            ->assertSee('(250 Kč)')
            ->assertDontSee('Samostatná lekce se prodává sama za sebe.');
    }

    /**
     * Without a drop-in price on the course, ReleaseFreeSpots skips the lesson
     * altogether — and a price filled in here does not change that. The tab has
     * to say so, because nothing else would ever surface it.
     */
    public function test_sale_tab_warns_when_the_course_does_not_price_single_seats(): void
    {
        $course = Course::factory()->create(['name' => 'Zdravá záda', 'drop_in_price' => null]);
        $series = CourseSeries::factory()->for($course, 'course')->create();
        $record = Lesson::factory()->for($series, 'series')->create();

        Livewire::test(EditLesson::class, ['record' => $record->getKey()])
            ->assertSee('Zdravá záda')
            ->assertSee('nemá vyplněnou')
            ->assertSee('ani když cenu vyplníte tady');
    }

    /**
     * A standalone lesson has no course to hang off, so it keeps Filament's
     * default trail through the Lekce list.
     */
    public function test_standalone_lesson_keeps_the_default_breadcrumbs(): void
    {
        $record = Lesson::factory()->create(['series_id' => null]);

        $breadcrumbs = Livewire::test(EditLesson::class, ['record' => $record->getKey()])
            ->instance()
            ->getBreadcrumbs();

        $this->assertContains(LessonResource::getUrl(), array_keys($breadcrumbs));
    }
}
