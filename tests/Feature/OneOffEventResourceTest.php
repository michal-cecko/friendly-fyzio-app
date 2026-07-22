<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages\CreateOneOffEvent;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages\EditOneOffEvent;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages\ListOneOffEvents;
use App\Models\Course;
use App\Models\EventCategory;
use App\Models\OneOffEvent;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OneOffEventResourceTest extends TestCase
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
        $records = OneOffEvent::factory()->count(3)->create();

        Livewire::test(ListOneOffEvents::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        $category = EventCategory::factory()->create();
        $instructor = User::factory()->lecturer()->create();
        $room = Room::factory()->create();

        Livewire::test(CreateOneOffEvent::class)
            ->fillForm([
                'event_category_id' => $category->id,
                'name' => 'Workshop dýchání',
                'slug' => 'workshop-dychani',
                'instructor_id' => $instructor->id,
                'room_id' => $room->id,
                'event_date' => '2026-03-15',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'capacity' => 15,
                'price' => 800,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(OneOffEvent::class, [
            'name' => 'Workshop dýchání',
            'slug' => 'workshop-dychani',
            'event_category_id' => $category->id,
            'instructor_id' => $instructor->id,
        ]);
    }

    public function test_admin_can_create_course_linked_record(): void
    {
        $category = EventCategory::factory()->create();
        $course = Course::factory()->create();
        $instructor = User::factory()->lecturer()->create();
        $room = Room::factory()->create();

        Livewire::test(CreateOneOffEvent::class)
            ->fillForm([
                'event_category_id' => $category->id,
                'course_id' => $course->id,
                'name' => 'Ochutnávková lekce',
                'slug' => 'ochutnavkova-lekce',
                'instructor_id' => $instructor->id,
                'room_id' => $room->id,
                'event_date' => '2026-03-20',
                'start_time' => '14:00',
                'end_time' => '15:00',
                'capacity' => 8,
                'price' => 500,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(OneOffEvent::class, [
            'slug' => 'ochutnavkova-lekce',
            'course_id' => $course->id,
            'event_date' => '2026-03-20 00:00:00',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateOneOffEvent::class)
            ->fillForm([
                'event_category_id' => null,
                'name' => null,
                'slug' => null,
                'instructor_id' => null,
                'event_date' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'event_category_id' => 'required',
                'name' => 'required',
                'slug' => 'required',
                'instructor_id' => 'required',
                'event_date' => 'required',
            ]);
    }

    public function test_admin_can_edit_record(): void
    {
        $record = OneOffEvent::factory()->create();

        Livewire::test(EditOneOffEvent::class, ['record' => $record->getKey()])
            ->fillForm([
                'name' => 'Aktualizovaná akce',
                'event_date' => '2026-04-25',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(OneOffEvent::class, [
            'id' => $record->id,
            'name' => 'Aktualizovaná akce',
            'event_date' => '2026-04-25 00:00:00',
        ]);
    }
}
