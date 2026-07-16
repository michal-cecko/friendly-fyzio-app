<?php

namespace Tests\Feature;

use App\Filament\Clusters\System\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminActsAsTherapistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_opting_in_auto_creates_an_unpublished_therapist_profile(): void
    {
        $admin = User::factory()->admin()->create(['acts_as_therapist' => true]);

        $profile = $admin->therapistProfile;

        $this->assertNotNull($profile);
        $this->assertFalse($profile->isPublished());
    }

    public function test_no_profile_is_created_without_the_flag_or_for_other_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $therapist = User::factory()->therapist()->create();
        $customer = User::factory()->customer()->create();

        $this->assertNull($admin->therapistProfile);
        $this->assertNull($therapist->therapistProfile);
        $this->assertNull($customer->therapistProfile);
    }

    public function test_is_therapist_and_scope_cover_opted_in_admins(): void
    {
        $therapist = User::factory()->therapist()->create();
        $actingAdmin = User::factory()->admin()->create(['acts_as_therapist' => true]);
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

    public function test_admin_can_opt_in_another_admin_via_the_user_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $admin = User::factory()->admin()->create();

        Livewire::test(EditUser::class, ['record' => $admin->getKey()])
            ->fillForm(['acts_as_therapist' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $admin->refresh();

        $this->assertTrue($admin->acts_as_therapist);
        $this->assertNotNull($admin->therapistProfile);
    }
}
