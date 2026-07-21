<?php

namespace Tests\Feature\Cms;

use App\Mason\Bricks\TeamBrick;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamBrickTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_users_with_a_published_profile_are_listed(): void
    {
        $published = User::factory()->therapist()->create(['name' => 'Petra Publikovaná']);
        StaffProfile::factory()->create(['user_id' => $published->getKey(), 'published_at' => now()->subDay()]);

        $draft = User::factory()->therapist()->create(['name' => 'Dana Rozpracovaná']);
        StaffProfile::factory()->create(['user_id' => $draft->getKey(), 'published_at' => null]);

        User::factory()->therapist()->create(['name' => 'Nora Bezprofilová']);

        $html = TeamBrick::toHtml([]);

        $this->assertStringContainsString('Petra Publikovaná', $html);
        $this->assertStringNotContainsString('Dana Rozpracovaná', $html);
        $this->assertStringNotContainsString('Nora Bezprofilová', $html);
    }

    public function test_opted_in_admin_with_published_profile_is_listed(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Alena Adminová', 'acts_as_therapist' => true]);
        $admin->staffProfile->update(['published_at' => now()->subDay()]);

        $html = TeamBrick::toHtml([]);

        $this->assertStringContainsString('Alena Adminová', $html);
    }
}
