<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\StaffProfiles\Pages\CreateStaffProfile;
use App\Filament\Clusters\System\Resources\Users\Pages\ViewUser;
use App\Filament\Clusters\System\Resources\Users\RelationManagers\StaffProfileRelationManager;
use App\Models\StaffProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffProfileResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_admin_can_create_therapist_profile_with_generated_slug(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $therapistUser = User::factory()->therapist()->create(['name' => 'Mgr. Lucie Fičkerová']);

        Livewire::test(CreateStaffProfile::class)
            ->fillForm([
                'user_id' => $therapistUser->getKey(),
                'title' => 'Fyzioterapeutka, zakladatelka',
                'published_at' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $profile = StaffProfile::where('user_id', $therapistUser->getKey())->first();

        $this->assertNotNull($profile);
        $this->assertSame('Fyzioterapeutka, zakladatelka', $profile->title);
        $this->assertStringContainsString('lucie-fickerova', $profile->slug); // auto-generated from the user's name
        $this->assertTrue($profile->isPublished());
    }

    public function test_profile_relation_manager_is_visible_only_for_therapists(): void
    {
        $therapist = User::factory()->therapist()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue(StaffProfileRelationManager::canViewForRecord($therapist, ViewUser::class));
        $this->assertFalse(StaffProfileRelationManager::canViewForRecord($admin, ViewUser::class));
    }
}
