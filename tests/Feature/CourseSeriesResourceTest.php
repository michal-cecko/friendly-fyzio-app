<?php

namespace Tests\Feature;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\CreateCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\EditCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ListCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseSeriesResourceTest extends TestCase
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
        $records = CourseSeries::factory()->count(3)->create();

        Livewire::test(ListCourseSeries::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        $course = Course::factory()->create();

        Livewire::test(CreateCourseSeries::class)
            ->fillForm([
                'course_id' => $course->id,
                'name' => 'Jarní běh 2026',
                'start_date' => '2026-03-01',
                'end_date' => '2026-05-31',
                'capacity' => 12,
                'price' => 2400,
                'status' => CourseSeriesStatus::Open->value,
                'visibility' => CourseSeriesVisibility::Private->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(CourseSeries::class, [
            'course_id' => $course->id,
            'name' => 'Jarní běh 2026',
            'status' => CourseSeriesStatus::Open->value,
            'visibility' => CourseSeriesVisibility::Private->value,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateCourseSeries::class)
            ->fillForm([
                'course_id' => null,
                'name' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'course_id' => 'required',
                'name' => 'required',
            ]);
    }

    public function test_admin_can_edit_record(): void
    {
        $record = CourseSeries::factory()->create();

        Livewire::test(EditCourseSeries::class, ['record' => $record->getKey()])
            ->fillForm(['name' => 'Aktualizovaný běh'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(CourseSeries::class, [
            'id' => $record->id,
            'name' => 'Aktualizovaný běh',
        ]);
    }

    /**
     * Only Upravit stays a standalone button on the série detail; everything
     * else collapses into the "Další akce" dropdown but must still resolve.
     */
    public function test_detail_groups_every_action_except_edit(): void
    {
        // Private, so the sign-up link action is actually available here.
        $record = CourseSeries::factory()->create(['visibility' => CourseSeriesVisibility::Private]);

        Livewire::test(ViewCourseSeries::class, ['record' => $record->getKey()])
            ->assertActionExists('edit')
            ->assertActionVisible('presaleLink')
            ->assertActionExists('delete')
            ->assertActionExists('activityLog');
    }

    public function test_grouped_delete_still_removes_the_series(): void
    {
        $record = CourseSeries::factory()->create();

        Livewire::test(ViewCourseSeries::class, ['record' => $record->getKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing(CourseSeries::class, ['id' => $record->id]);
    }
}
