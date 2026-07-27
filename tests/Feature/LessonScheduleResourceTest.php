<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\EditLesson;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\ListLessons;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LessonScheduleResourceTest extends TestCase
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
        $series = CourseSeries::factory()->create();
        $instructor = User::factory()->lecturer()->create();
        $room = Room::factory()->create();

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'series_id' => $series->id,
                'instructor_id' => $instructor->id,
                'room_id' => $room->id,
                'lesson_date' => '2026-03-10',
                'start_time' => '09:00',
                'end_time' => '10:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Lesson::class, [
            'series_id' => $series->id,
            'instructor_id' => $instructor->id,
            'lesson_date' => '2026-03-10 00:00:00',
        ]);
    }

    /**
     * A lesson always needs a time, a room and somebody to teach it. The série
     * is optional — without one it is a standalone offer, which is why the
     * sellable fields become required instead (covered by LessonResourceTest).
     */
    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateLesson::class)
            ->fillForm([
                'series_id' => null,
                'instructor_id' => null,
                'lesson_date' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'instructor_id' => 'required',
                'lesson_date' => 'required',
            ])
            ->assertHasNoFormErrors(['series_id']);
    }

    public function test_admin_can_edit_record(): void
    {
        $record = Lesson::factory()->create();

        Livewire::test(EditLesson::class, ['record' => $record->getKey()])
            ->fillForm(['lesson_date' => '2026-04-15'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Lesson::class, [
            'id' => $record->id,
            'lesson_date' => '2026-04-15 00:00:00',
        ]);
    }
}
