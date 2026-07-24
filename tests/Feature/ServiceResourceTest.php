<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\Services\Pages\CreateService;
use App\Filament\Clusters\Provoz\Resources\Services\Pages\EditService;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Models\Building;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    protected function superAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function makeRoom(): Room
    {
        $building = Building::create(['name' => 'Budova A', 'address' => 'Adresa 1']);

        return Room::create(['building_id' => $building->getKey(), 'name' => 'Sál 1']);
    }

    public function test_admin_can_view_services_list(): void
    {
        $this->actingAs($this->superAdmin())->get('/admin/provoz/services')->assertSuccessful();
    }

    public function test_service_detail_page_renders_all_sections(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->superAdmin())
            ->get(ServiceResource::getUrl('view', ['record' => $service]))
            ->assertSuccessful()
            ->assertSee('Základní údaje')
            ->assertSee('Délka a cena')
            ->assertSee('Storno podmínky')
            ->assertSee('Viditelnost a publikování')
            ->assertSee('Místnosti a terapeuti');
    }

    public function test_edit_page_loads_with_attached_therapists(): void
    {
        $service = Service::factory()->create();
        $profile = User::factory()->therapist()->create()->staffProfile;
        $service->therapists()->attach($profile);

        $this->actingAs($this->superAdmin());

        Livewire::test(EditService::class, ['record' => $service->getKey()])
            ->assertSuccessful()
            ->assertFormSet(['therapists' => [$profile->getKey()]]);
    }

    public function test_service_creation_validates_required_fields(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => null,
                'slug' => null,
                'duration_minutes' => null,
                'price' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'slug' => 'required',
                'duration_minutes' => 'required',
                'price' => 'required',
            ]);
    }

    public function test_can_create_service_with_attached_room(): void
    {
        $room = $this->makeRoom();

        $this->actingAs($this->superAdmin());

        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Masáž',
                'slug' => 'masaz',
                'duration_minutes' => 60,
                'break_minutes' => 15,
                'price' => 800,
                'visibility' => 'public',
                'rooms' => [$room->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::where('slug', 'masaz')->first();

        $this->assertNotNull($service);
        $this->assertSame(60, $service->duration_minutes);
        $this->assertSame(15, $service->break_minutes);
        $this->assertTrue($service->rooms->contains($room));
    }
}
