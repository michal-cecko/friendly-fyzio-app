<?php

namespace Tests\Feature;

use App\Livewire\PublicLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PublicLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_renders(): void
    {
        $this->get(route('public.login'))
            ->assertOk()
            ->assertSee('Přihlášení');
    }

    public function test_valid_credentials_authenticate_and_return_to_the_wizard(): void
    {
        $user = User::factory()->customer()->create(['email' => 'a@b.com', 'password' => Hash::make('tajneheslo')]);

        Livewire::test(PublicLogin::class)
            ->set('return', '/rezervace?kategorie=masaze')
            ->set('email', 'a@b.com')
            ->set('password', 'tajneheslo')
            ->call('authenticate')
            ->assertRedirect('/rezervace?kategorie=masaze');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_show_an_error(): void
    {
        User::factory()->customer()->create(['email' => 'a@b.com', 'password' => Hash::make('tajneheslo')]);

        Livewire::test(PublicLogin::class)
            ->set('email', 'a@b.com')
            ->set('password', 'wrong')
            ->call('authenticate')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_external_return_urls_are_rejected_and_default_to_the_client_zone(): void
    {
        $user = User::factory()->customer()->create(['email' => 'a@b.com', 'password' => Hash::make('tajneheslo')]);

        Livewire::test(PublicLogin::class)
            ->set('return', 'https://evil.example.com')
            ->set('email', 'a@b.com')
            ->set('password', 'tajneheslo')
            ->call('authenticate')
            ->assertRedirect('/muj-ucet');

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_are_redirected_to_the_admin_panel(): void
    {
        User::factory()->admin()->create(['email' => 'sef@b.com', 'password' => Hash::make('tajneheslo')]);

        Livewire::test(PublicLogin::class)
            ->set('email', 'sef@b.com')
            ->set('password', 'tajneheslo')
            ->call('authenticate')
            ->assertRedirect('/admin');
    }

    public function test_deactivated_customers_cannot_sign_in(): void
    {
        User::factory()->customer()->create([
            'email' => 'a@b.com',
            'password' => Hash::make('tajneheslo'),
            'deactivated_at' => now(),
        ]);

        Livewire::test(PublicLogin::class)
            ->set('email', 'a@b.com')
            ->set('password', 'tajneheslo')
            ->call('authenticate')
            ->assertHasErrors('email');

        $this->assertGuest();
    }
}
