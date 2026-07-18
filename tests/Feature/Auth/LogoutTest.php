<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_signs_the_user_out_and_redirects_home(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->post('/odhlaseni')
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
