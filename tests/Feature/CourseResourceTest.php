<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\CreateCourse;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\EditCourse;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\ListCourses;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\ViewCourse;
use App\Models\Course;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseResourceTest extends TestCase
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
        $records = Course::factory()->count(3)->create();

        Livewire::test(ListCourses::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        $instructor = User::factory()->lecturer()->create();

        Livewire::test(CreateCourse::class)
            ->fillForm([
                'name' => 'Pilates pro začátečníky',
                'slug' => 'pilates-pro-zacatecniky',
                'instructor_id' => $instructor->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Course::class, [
            'name' => 'Pilates pro začátečníky',
            'slug' => 'pilates-pro-zacatecniky',
            'instructor_id' => $instructor->id,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateCourse::class)
            ->fillForm([
                'name' => null,
                'slug' => null,
                'instructor_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'slug' => 'required',
                'instructor_id' => 'required',
            ]);
    }

    public function test_admin_can_edit_record(): void
    {
        $record = Course::factory()->create();

        Livewire::test(EditCourse::class, ['record' => $record->getKey()])
            ->fillForm(['name' => 'Aktualizovaný název'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Course::class, [
            'id' => $record->id,
            'name' => 'Aktualizovaný název',
        ]);
    }

    /**
     * The instructor picker restricts its options to lecturers, but a course
     * may already be assigned to someone who isn't one (e.g. a therapist from
     * a historical import). The selected label must still resolve to the name
     * rather than falling back to the raw UUID.
     */
    public function test_edit_shows_instructor_name_even_when_not_a_lecturer(): void
    {
        $therapist = User::factory()->therapist()->create(['name' => 'Mgr. Anna Kovaříková']);
        $record = Course::factory()->create(['instructor_id' => $therapist->id]);

        Livewire::test(EditCourse::class, ['record' => $record->getKey()])
            ->assertSee('Mgr. Anna Kovaříková');
    }

    /**
     * The detail header keeps only the public-page link and Upravit standalone;
     * everything else collapses into the "Další akce" dropdown that sits last.
     */
    public function test_detail_header_groups_delete_and_history_under_the_dropdown(): void
    {
        $record = Course::factory()->create();

        $page = Livewire::test(ViewCourse::class, ['record' => $record->getKey()])->instance();
        $headerActions = (fn (): array => $this->getHeaderActions())->call($page);

        $this->assertCount(3, $headerActions);
        $this->assertSame('visit', $headerActions[0]->getName());
        $this->assertInstanceOf(EditAction::class, $headerActions[1]);

        $group = $headerActions[2];
        $this->assertInstanceOf(ActionGroup::class, $group);
        $this->assertSame('Další akce', $group->getLabel());
        $this->assertSame(
            ['delete', 'activityLog'],
            array_map(fn ($action): string => $action->getName(), $group->getActions()),
        );
    }

    public function test_grouped_delete_action_still_deletes_the_record(): void
    {
        $record = Course::factory()->create();

        Livewire::test(ViewCourse::class, ['record' => $record->getKey()])
            ->callAction('delete');

        $this->assertSoftDeleted($record);
    }

    /**
     * Course exposes its permalink as a plain method rather than the
     * HasPermalink attribute, so the shared action has to resolve both.
     */
    public function test_detail_links_to_the_public_course_page(): void
    {
        $record = Course::factory()->create(['slug' => 'pilates-pro-zacatecniky']);

        Livewire::test(ViewCourse::class, ['record' => $record->getKey()])
            ->assertActionExists('visit')
            ->assertActionHasUrl('visit', url('/kurzy/pilates-pro-zacatecniky'));
    }

    public function test_detail_shows_the_drop_in_price(): void
    {
        $record = Course::factory()->create(['drop_in_price' => 250]);

        Livewire::test(ViewCourse::class, ['record' => $record->getKey()])
            ->assertSee('Cena jednorázového vstupu')
            ->assertSee('250');
    }
}
