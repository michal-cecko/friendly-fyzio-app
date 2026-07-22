<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\CreateCourse;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\EditCourse;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\ListCourses;
use App\Models\Course;
use App\Models\User;
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
}
