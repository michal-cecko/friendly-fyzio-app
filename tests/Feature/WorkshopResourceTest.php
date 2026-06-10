<?php

namespace Tests\Feature;

use App\Filament\Resources\Workshops\Pages\CreateWorkshop;
use App\Filament\Resources\Workshops\Pages\EditWorkshop;
use App\Filament\Resources\Workshops\Pages\ListWorkshops;
use App\Models\Room;
use App\Models\User;
use App\Models\Workshop;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkshopResourceTest extends TestCase
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
        $records = Workshop::factory()->count(3)->create();

        Livewire::test(ListWorkshops::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        $instructor = User::factory()->therapist()->create();
        $room = Room::factory()->create();

        Livewire::test(CreateWorkshop::class)
            ->fillForm([
                'name' => 'Workshop dýchání',
                'slug' => 'workshop-dychani',
                'instructor_id' => $instructor->id,
                'room_id' => $room->id,
                'workshop_date' => '2026-03-15',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'capacity' => 15,
                'price' => 800,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Workshop::class, [
            'name' => 'Workshop dýchání',
            'slug' => 'workshop-dychani',
            'instructor_id' => $instructor->id,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateWorkshop::class)
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
        $record = Workshop::factory()->create();

        Livewire::test(EditWorkshop::class, ['record' => $record->getKey()])
            ->fillForm(['name' => 'Aktualizovaný workshop'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Workshop::class, [
            'id' => $record->id,
            'name' => 'Aktualizovaný workshop',
        ]);
    }
}
