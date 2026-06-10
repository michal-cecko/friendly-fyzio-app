<?php

namespace Tests\Feature;

use App\Filament\Resources\Rooms\Pages\EditRoom;
use App\Filament\Resources\Rooms\RelationManagers\BlockingsRelationManager;
use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class RoomBlockingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    protected function makeRoom(): Room
    {
        $building = Building::create(['name' => 'Budova A', 'address' => 'Adresa 1']);

        return Room::create(['building_id' => $building->getKey(), 'name' => 'Sál 1']);
    }

    public function test_can_create_recurring_blocking(): void
    {
        $room = $this->makeRoom();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(BlockingsRelationManager::class, [
            'ownerRecord' => $room,
            'pageClass' => EditRoom::class,
        ])
            ->callAction(TestAction::make('create')->table(), data: [
                'reason' => 'Pravidelná údržba',
                'is_recurring' => true,
                'day_of_week' => 'monday',
                'week_type' => 'all',
                'start_time' => '08:00',
                'end_time' => '12:00',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('room_blockings', [
            'room_id' => $room->getKey(),
            'is_recurring' => true,
            'day_of_week' => 'monday',
        ]);
    }

    public function test_can_create_one_off_blocking(): void
    {
        $room = $this->makeRoom();
        $this->actingAs(User::factory()->admin()->create());

        $start = Carbon::now()->addDay()->startOfHour();

        Livewire::test(BlockingsRelationManager::class, [
            'ownerRecord' => $room,
            'pageClass' => EditRoom::class,
        ])
            ->callAction(TestAction::make('create')->table(), data: [
                'reason' => 'Jednorázová akce',
                'is_recurring' => false,
                'start_at' => $start->toDateTimeString(),
                'end_at' => $start->copy()->addHours(2)->toDateTimeString(),
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('room_blockings', [
            'room_id' => $room->getKey(),
            'is_recurring' => false,
            'reason' => 'Jednorázová akce',
        ]);
    }
}
