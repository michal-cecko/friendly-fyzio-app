<?php

namespace Tests\Feature;

use App\Livewire\PublicLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function assertLoginRedirects(User $user, string $target): void
    {
        Livewire::test(PublicLogin::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect($target);
    }

    public function test_admin_is_redirected_to_the_admin_panel(): void
    {
        $this->assertLoginRedirects(User::factory()->admin()->create(), '/admin');
    }

    public function test_therapist_is_redirected_to_the_admin_panel(): void
    {
        $this->assertLoginRedirects(User::factory()->therapist()->create(), '/admin');
    }

    public function test_customer_is_redirected_to_the_client_zone(): void
    {
        $this->assertLoginRedirects(User::factory()->customer()->create(), '/muj-ucet');
    }
}
