<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Capabilities compose: an admin who also practises simply holds the Therapist
 * capability alongside Admin. This covers what the old `acts_as_therapist` flag
 * expressed, now as a first-class capability.
 */
class AdminActsAsTherapistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_therapist_and_lecturer_capabilities_auto_create_an_unpublished_profile(): void
    {
        foreach ([Capability::Therapist, Capability::Lecturer] as $capability) {
            $user = User::factory()->create();
            $user->grantCapability($capability);

            $this->assertNotNull($user->staffProfile, $capability->value.' should get a profile');
            $this->assertFalse($user->staffProfile->isPublished());
        }
    }

    public function test_pure_admins_and_customers_get_no_profile(): void
    {
        $this->assertNull(User::factory()->admin()->create()->staffProfile);
        $this->assertNull(User::factory()->customer()->create()->staffProfile);
    }

    public function test_is_therapist_and_scope_cover_admins_who_also_practise(): void
    {
        $therapist = User::factory()->therapist()->create();
        $actingAdmin = User::factory()->admin()->therapist()->create();
        $plainAdmin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();

        $this->assertTrue($therapist->isTherapist());
        $this->assertTrue($actingAdmin->isTherapist());
        $this->assertFalse($plainAdmin->isTherapist());
        $this->assertFalse($customer->isTherapist());

        $ids = User::query()->therapists()->pluck('id');

        $this->assertTrue($ids->contains($therapist->getKey()));
        $this->assertTrue($ids->contains($actingAdmin->getKey()));
        $this->assertFalse($ids->contains($plainAdmin->getKey()));
        $this->assertFalse($ids->contains($customer->getKey()));
    }

    public function test_a_plain_admin_can_add_the_therapist_capability_via_the_form(): void
    {
        // Therapist is an operational capability any admin may assign.
        $this->actingAs(User::factory()->admin()->create());

        $target = User::factory()->lecturer()->create();

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm(['capabilities' => [Capability::Therapist->value]])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();

        $this->assertTrue($target->isTherapist());
        $this->assertNotNull($target->staffProfile);
    }

    public function test_a_super_admin_can_compose_admin_and_therapist_via_the_form(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->grantCapability(Capability::SuperAdmin);
        $this->actingAs($superAdmin);

        $target = User::factory()->lecturer()->create();

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm(['capabilities' => [Capability::Admin->value, Capability::Therapist->value]])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();

        $this->assertTrue($target->isAdmin());
        $this->assertTrue($target->isTherapist());
        $this->assertNotNull($target->staffProfile);
    }
}
