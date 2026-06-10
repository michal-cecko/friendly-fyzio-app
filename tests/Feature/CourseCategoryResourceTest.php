<?php

namespace Tests\Feature;

use App\Filament\Resources\CourseCategories\Pages\CreateCourseCategory;
use App\Filament\Resources\CourseCategories\Pages\EditCourseCategory;
use App\Filament\Resources\CourseCategories\Pages\ListCourseCategories;
use App\Models\CourseCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseCategoryResourceTest extends TestCase
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
        $records = CourseCategory::factory()->count(3)->create();

        Livewire::test(ListCourseCategories::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        Livewire::test(CreateCourseCategory::class)
            ->fillForm([
                'name' => 'Rehabilitace',
                'slug' => 'rehabilitace',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(CourseCategory::class, [
            'name' => 'Rehabilitace',
            'slug' => 'rehabilitace',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateCourseCategory::class)
            ->fillForm([
                'name' => null,
                'slug' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'slug' => 'required',
            ]);
    }

    public function test_admin_can_edit_record(): void
    {
        $record = CourseCategory::factory()->create();

        Livewire::test(EditCourseCategory::class, ['record' => $record->getKey()])
            ->fillForm(['name' => 'Aktualizovaná kategorie'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(CourseCategory::class, [
            'id' => $record->id,
            'name' => 'Aktualizovaná kategorie',
        ]);
    }
}
