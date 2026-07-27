<?php

namespace Tests\Feature;

use App\Enums\LessonExcuseReason;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\LessonAttendanceResource;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages\ListLessonAttendances;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages\ViewLessonAttendance;
use App\Models\LessonAttendance;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The standalone docházka resource is a read-only lookup: seats are created by
 * enrolling or by buying one, presence is changed from the lesson's own roster,
 * and the only thing editable here is the note on an absence.
 */
class LessonAttendanceResourceTest extends TestCase
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
        $records = LessonAttendance::factory()->count(3)->create();

        Livewire::test(ListLessonAttendances::class)
            ->assertCanSeeTableRecords($records);
    }

    /**
     * A drop-in has no enrollment behind their seat, so anything reading the
     * client through one shows an empty row.
     */
    public function test_a_drop_in_seat_names_its_client(): void
    {
        $attendance = LessonAttendance::factory()->dropIn()->create();

        $this->assertNull($attendance->enrollment_id);

        Livewire::test(ListLessonAttendances::class)
            ->assertCanSeeTableRecords([$attendance])
            ->assertSee($attendance->client->name);

        Livewire::test(ViewLessonAttendance::class, ['record' => $attendance->getKey()])
            ->assertSee($attendance->client->name)
            ->assertSee('Jednorázový vstup');
    }

    public function test_admin_can_amend_an_absence(): void
    {
        $attendance = LessonAttendance::factory()->excused()->create();

        Livewire::test(ViewLessonAttendance::class, ['record' => $attendance->getKey()])
            ->callAction(TestAction::make('editExcuse'), [
                'excuse_reason' => LessonExcuseReason::Injury->value,
                'excuse_note' => 'Volala, vrátí se za dva týdny.',
            ])
            ->assertHasNoActionErrors();

        $attendance->refresh();

        $this->assertSame(LessonExcuseReason::Injury, $attendance->excuse_reason);
        $this->assertSame('Volala, vrátí se za dva týdny.', $attendance->excuse_note);
    }

    /**
     * There is nothing to amend on somebody who is coming — and the action must
     * not become a back door to marking them absent without the náhrada rules.
     */
    public function test_the_excuse_action_is_hidden_on_a_present_seat(): void
    {
        $attendance = LessonAttendance::factory()->attended()->create();

        Livewire::test(ViewLessonAttendance::class, ['record' => $attendance->getKey()])
            ->assertActionHidden(TestAction::make('editExcuse'));
    }

    /**
     * Presence and creation belong to the lesson's roster and to the sign-up
     * flows; this resource must not offer a form that bypasses either.
     */
    public function test_the_resource_has_no_create_or_edit_pages(): void
    {
        $pages = array_keys(
            LessonAttendanceResource::getPages(),
        );

        $this->assertSame(['index', 'view'], $pages);
    }
}
