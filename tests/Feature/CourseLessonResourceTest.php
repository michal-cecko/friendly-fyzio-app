<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages\CreateCourseLesson;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages\EditCourseLesson;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages\ListCourseLessons;
use App\Models\CourseLesson;
use App\Models\CourseSeries;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseLessonResourceTest extends TestCase
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
        $records = CourseLesson::factory()->count(3)->create();

        Livewire::test(ListCourseLessons::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        $series = CourseSeries::factory()->create();
        $instructor = User::factory()->therapist()->create();
        $room = Room::factory()->create();

        Livewire::test(CreateCourseLesson::class)
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

        $this->assertDatabaseHas(CourseLesson::class, [
            'series_id' => $series->id,
            'instructor_id' => $instructor->id,
            'lesson_date' => '2026-03-10 00:00:00',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateCourseLesson::class)
            ->fillForm([
                'series_id' => null,
                'instructor_id' => null,
                'lesson_date' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'series_id' => 'required',
                'instructor_id' => 'required',
                'lesson_date' => 'required',
            ]);
    }

    public function test_admin_can_edit_record(): void
    {
        $record = CourseLesson::factory()->create();

        Livewire::test(EditCourseLesson::class, ['record' => $record->getKey()])
            ->fillForm(['lesson_date' => '2026-04-15'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(CourseLesson::class, [
            'id' => $record->id,
            'lesson_date' => '2026-04-15 00:00:00',
        ]);
    }
}
