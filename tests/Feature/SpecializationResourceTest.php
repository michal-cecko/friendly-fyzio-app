<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\Services\Pages\EditService;
use App\Filament\Clusters\Provoz\Resources\Specializations\Pages\EditSpecialization;
use App\Filament\Clusters\Provoz\Resources\Specializations\Pages\ListSpecializations;
use App\Filament\Clusters\Provoz\Resources\Specializations\SpecializationResource;
use App\Models\Service;
use App\Models\Specialization;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The catalogue's service mapping is what turns a specialization into a booking
 * link on the therapist's profile, so it has to be editable from both ends: the
 * specialization itself, and the service it belongs under.
 */
class SpecializationResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_view_the_specializations_list(): void
    {
        $specialization = Specialization::factory()->create(['name' => 'Terapie jizev']);

        $this->actingAs($this->admin())
            ->get(SpecializationResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee($specialization->name);
    }

    public function test_the_unmapped_filter_separates_entries_without_a_service(): void
    {
        $this->actingAs($this->admin());

        $mapped = Specialization::factory()->create(['service_id' => Service::factory()->create()]);
        $unmapped = Specialization::factory()->create(['service_id' => null]);

        Livewire::test(ListSpecializations::class)
            ->assertCanSeeTableRecords([$mapped, $unmapped])
            ->filterTable('mapped', false)
            ->assertCanSeeTableRecords([$unmapped])
            ->assertCanNotSeeTableRecords([$mapped]);
    }

    public function test_the_service_mapping_is_saved_from_the_specialization_form(): void
    {
        $this->actingAs($this->admin());

        $specialization = Specialization::factory()->create(['service_id' => null]);
        $service = Service::factory()->create();

        Livewire::test(EditSpecialization::class, ['record' => $specialization->getKey()])
            ->fillForm(['service_id' => $service->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($specialization->refresh()->service->is($service));
    }

    public function test_an_existing_specialization_can_be_attached_from_the_service_form(): void
    {
        $this->actingAs($this->admin());

        $service = Service::factory()->create();
        $specialization = Specialization::factory()->create(['service_id' => null]);

        Livewire::test(EditService::class, ['record' => $service->getKey()])
            ->callAction(TestAction::make('attachSpecialization')->schemaComponent('specializations'), [
                'specialization_id' => $specialization->getKey(),
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue($specialization->refresh()->service->is($service));
    }

    public function test_the_navigation_badge_counts_only_unmapped_entries(): void
    {
        Specialization::factory()->create(['service_id' => Service::factory()->create()]);
        Specialization::factory()->count(2)->create(['service_id' => null]);

        $this->assertSame('2', SpecializationResource::getNavigationBadge());
    }
}
