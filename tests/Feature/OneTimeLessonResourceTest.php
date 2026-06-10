<?php

namespace Tests\Feature;

use App\Filament\Resources\OneTimeLessons\Pages\CreateOneTimeLesson;
use App\Filament\Resources\OneTimeLessons\Pages\EditOneTimeLesson;
use App\Filament\Resources\OneTimeLessons\Pages\ListOneTimeLessons;
use App\Models\Course;
use App\Models\OneTimeLesson;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OneTimeLessonResourceTest extends TestCase
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
        $records = OneTimeLesson::factory()->count(3)->create();

        Livewire::test(ListOneTimeLessons::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        $course = Course::factory()->create();
        $instructor = User::factory()->therapist()->create();
        $room = Room::factory()->create();

        Livewire::test(CreateOneTimeLesson::class)
            ->fillForm([
                'course_id' => $course->id,
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

        $this->assertDatabaseHas(OneTimeLesson::class, [
            'course_id' => $course->id,
            'instructor_id' => $instructor->id,
            'lesson_date' => '2026-03-20 00:00:00',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateOneTimeLesson::class)
            ->fillForm([
                'course_id' => null,
                'instructor_id' => null,
                'lesson_date' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'course_id' => 'required',
                'instructor_id' => 'required',
                'lesson_date' => 'required',
            ]);
    }

    public function test_admin_can_edit_record(): void
    {
        $record = OneTimeLesson::factory()->create();

        Livewire::test(EditOneTimeLesson::class, ['record' => $record->getKey()])
            ->fillForm(['lesson_date' => '2026-04-25'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(OneTimeLesson::class, [
            'id' => $record->id,
            'lesson_date' => '2026-04-25 00:00:00',
        ]);
    }
}
