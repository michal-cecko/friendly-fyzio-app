<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\Rooms\Pages\ViewRoom;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoomResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_room_detail_shows_placeholder_when_no_services_are_assigned(): void
    {
        $room = Room::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ViewRoom::class, ['record' => $room->getKey()])
            ->assertSee('Žádné služby se v této místnosti neposkytují');
    }

    public function test_room_detail_lists_assigned_services(): void
    {
        $room = Room::factory()->create();
        $service = Service::factory()->create();
        $room->services()->attach($service);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ViewRoom::class, ['record' => $room->getKey()])
            ->assertSee($service->name)
            ->assertDontSee('Žádné služby se v této místnosti neposkytují');
    }
}
