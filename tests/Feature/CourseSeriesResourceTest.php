<?php

namespace Tests\Feature;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\WaitlistPromotionMode;
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
     * The edit form splits its fields across the Základní údaje / Přihlašování
     * tabs; saving must still carry fields from both of them.
     */
    public function test_edit_saves_fields_from_both_form_tabs(): void
    {
        $record = CourseSeries::factory()->create([
            'status' => CourseSeriesStatus::Open,
            'visibility' => CourseSeriesVisibility::Public,
            'capacity' => 8,
        ]);

        Livewire::test(EditCourseSeries::class, ['record' => $record->getKey()])
            ->fillForm([
                'name' => 'Podzimní běh',
                'price' => 3300,
                'capacity' => 15,
                'status' => CourseSeriesStatus::Inactive->value,
                'visibility' => CourseSeriesVisibility::Private->value,
                'waitlist_promotion_mode' => WaitlistPromotionMode::AutomaticInvite->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(CourseSeries::class, [
            'id' => $record->id,
            'name' => 'Podzimní běh',
            'price' => 3300,
            'capacity' => 15,
            'status' => CourseSeriesStatus::Inactive->value,
            'visibility' => CourseSeriesVisibility::Private->value,
            'waitlist_promotion_mode' => WaitlistPromotionMode::AutomaticInvite->value,
        ]);
    }

    /**
     * The tabs render several fields per row through Tailwind container
     * queries, which only resolve inside an element carrying `fi-grid-ctn`.
     * That element comes from `gridContainer()` on the Tabs component — a Tab
     * renders no wrapper of its own, so declaring it there would silently drop
     * the form back to one field per row.
     */
    public function test_form_tabs_render_inside_a_grid_container(): void
    {
        $record = CourseSeries::factory()->create();

        $html = Livewire::test(EditCourseSeries::class, ['record' => $record->getKey()])->html();

        $this->assertMatchesRegularExpression(
            '/class="[^"]*fi-grid-ctn[^"]*"[^>]*>\s*<div[^>]*class="[^"]*fi-sc-tabs/',
            $html,
            'The série form Tabs are not wrapped in a fi-grid-ctn element, so the @-breakpoint column spans never apply.',
        );

        $this->assertStringContainsString('@xl:fi-grid-cols', $html);
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
