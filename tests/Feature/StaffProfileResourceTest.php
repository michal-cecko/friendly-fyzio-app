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

    public function test_admin_can_create_a_profile_for_a_staff_member_without_one(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // A pure admin (the assistant case) has no auto-created profile, so the
        // manual create flow adds one and generates the slug from their name.
        $assistant = User::factory()->admin()->create(['name' => 'Mgr. Lucie Fičkerová']);
        $this->assertNull($assistant->staffProfile);

        Livewire::test(CreateStaffProfile::class)
            ->fillForm([
                'user_id' => $assistant->getKey(),
                'title' => 'Fyzioterapeutka, zakladatelka',
                'published_at' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $profile = StaffProfile::where('user_id', $assistant->getKey())->first();

        $this->assertNotNull($profile);
        $this->assertSame('Fyzioterapeutka, zakladatelka', $profile->title);
        $this->assertStringContainsString('lucie-fickerova', $profile->slug); // auto-generated from the user's name
        $this->assertTrue($profile->isPublished());
    }

    public function test_a_therapist_gets_an_auto_created_profile_with_a_slug(): void
    {
        $therapist = User::factory()->therapist()->create(['name' => 'Jana Terapeutka']);

        $this->assertNotNull($therapist->staffProfile);
        $this->assertStringContainsString('jana-terapeutka', $therapist->staffProfile->slug);
    }

    public function test_profile_relation_manager_is_visible_only_for_therapists(): void
    {
        $therapist = User::factory()->therapist()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue(StaffProfileRelationManager::canViewForRecord($therapist, ViewUser::class));
        $this->assertFalse(StaffProfileRelationManager::canViewForRecord($admin, ViewUser::class));
    }
}
