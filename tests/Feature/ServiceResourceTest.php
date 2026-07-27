<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\Services\Pages\CreateService;
use App\Filament\Clusters\Provoz\Resources\Services\Pages\EditService;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Models\Building;
use App\Models\Room;
use App\Models\Service;
use App\Models\StaffProfile;
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
        $service->therapists()->attach($profile, ['break_blocks' => 2]);

        $this->actingAs($this->superAdmin());

        Livewire::test(EditService::class, ['record' => $service->getKey()])
            ->assertSuccessful()
            ->assertFormSet(fn (array $state): bool => collect($state['serviceTherapists'])
                ->contains(fn (array $row): bool => $row['therapist_id'] === $profile->getKey()
                    && (int) $row['break_blocks'] === 2));
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
                'price' => 800,
                'visibility' => 'public',
                'rooms' => [$room->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::where('slug', 'masaz')->first();

        $this->assertNotNull($service);
        $this->assertSame(60, $service->duration_minutes);
        $this->assertTrue($service->rooms->contains($room));
    }

    public function test_can_assign_therapists_with_and_without_a_break_override(): void
    {
        $withOverride = StaffProfile::factory()->create(['break_blocks' => 1]);
        $inheriting = StaffProfile::factory()->create(['break_blocks' => 1]);

        $this->actingAs($this->superAdmin());

        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Masáž zad',
                'slug' => 'masaz-zad',
                'duration_minutes' => 60,
                'price' => 800,
                'visibility' => 'public',
                'serviceTherapists' => [
                    ['therapist_id' => $withOverride->getKey(), 'break_blocks' => 2],
                    ['therapist_id' => $inheriting->getKey(), 'break_blocks' => null],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::where('slug', 'masaz-zad')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$withOverride->getKey(), $inheriting->getKey()],
            $service->therapists->pluck('id')->all(),
        );

        // An override is stored as given; an empty one stays null so the
        // therapist's own default keeps applying.
        $this->assertSame(2, $service->therapists->firstWhere('id', $withOverride->getKey())->pivot->break_blocks);
        $this->assertNull($service->therapists->firstWhere('id', $inheriting->getKey())->pivot->break_blocks);
    }

    public function test_a_break_override_survives_a_round_trip_through_the_edit_form(): void
    {
        $therapist = StaffProfile::factory()->create(['break_blocks' => 1]);
        $service = Service::factory()->create();
        $service->therapists()->attach($therapist, ['break_blocks' => 2]);

        $this->actingAs($this->superAdmin());

        Livewire::test(EditService::class, ['record' => $service->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, $service->fresh()->therapists->first()->pivot->break_blocks);
    }
}
