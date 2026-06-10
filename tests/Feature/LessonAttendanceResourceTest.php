<?php

namespace Tests\Feature;

use App\Filament\Resources\LessonAttendances\Pages\CreateLessonAttendance;
use App\Filament\Resources\LessonAttendances\Pages\EditLessonAttendance;
use App\Filament\Resources\LessonAttendances\Pages\ListLessonAttendances;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonAttendance;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

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

    public function test_admin_can_create_record(): void
    {
        $enrollment = CourseEnrollment::factory()->create();
        $lesson = CourseLesson::factory()->create();

        Livewire::test(CreateLessonAttendance::class)
            ->fillForm([
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $lesson->id,
                'attended' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(LessonAttendance::class, [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'attended' => true,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateLessonAttendance::class)
            ->fillForm([
                'enrollment_id' => null,
                'lesson_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'enrollment_id' => 'required',
                'lesson_id' => 'required',
            ]);
    }

    public function test_admin_can_edit_record(): void
    {
        $record = LessonAttendance::factory()->create(['attended' => false]);

        Livewire::test(EditLessonAttendance::class, ['record' => $record->getKey()])
            ->fillForm(['attended' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(LessonAttendance::class, [
            'id' => $record->id,
            'attended' => true,
        ]);
    }
}
