<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('client');
    }

    private function assertLoginRedirects(User $user, string $panelId): void
    {
        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(Filament::getPanel($panelId)->getUrl());
    }

    public function test_admin_is_redirected_to_the_admin_panel(): void
    {
        $this->assertLoginRedirects(User::factory()->admin()->create(), 'admin');
    }

    public function test_therapist_is_redirected_to_the_admin_panel(): void
    {
        $this->assertLoginRedirects(User::factory()->therapist()->create(), 'admin');
    }

    public function test_customer_is_redirected_to_the_client_zone(): void
    {
        $this->assertLoginRedirects(User::factory()->customer()->create(), 'client');
    }
}
