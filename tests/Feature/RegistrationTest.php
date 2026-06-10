<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Auth\Register;
use App\Models\ClientProfile;
use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('client');
    }

    private function fakeTurnstilePasses(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);
    }

    public function test_registration_creates_an_unverified_customer_with_a_client_profile(): void
    {
        $this->fakeTurnstilePasses();
        Notification::fake();

        Livewire::test(Register::class)
            ->fillForm([
                'first_name' => 'Jan',
                'last_name' => 'Novák',
                'email' => 'jan@example.com',
                'phone' => '+420 777 888 999',
                'password' => 'password1234',
                'passwordConfirmation' => 'password1234',
                'turnstile_token' => 'dummy-token',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'jan@example.com')->firstOrFail();

        $this->assertSame('Jan Novák', $user->name);
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas(ClientProfile::class, ['user_id' => $user->id]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_requires_a_turnstile_token(): void
    {
        Livewire::test(Register::class)
            ->fillForm([
                'first_name' => 'Eva',
                'last_name' => 'Malá',
                'email' => 'eva@example.com',
                'phone' => '+420 777 111 222',
                'password' => 'password1234',
                'passwordConfirmation' => 'password1234',
                'turnstile_token' => null,
            ])
            ->call('register')
            ->assertHasFormErrors(['turnstile_token']);

        $this->assertDatabaseMissing(User::class, ['email' => 'eva@example.com']);
    }

    public function test_registration_rejects_an_invalid_turnstile_token(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false]),
        ]);

        Livewire::test(Register::class)
            ->fillForm([
                'first_name' => 'Petr',
                'last_name' => 'Veliký',
                'email' => 'petr@example.com',
                'phone' => '+420 777 333 444',
                'password' => 'password1234',
                'passwordConfirmation' => 'password1234',
                'turnstile_token' => 'bad-token',
            ])
            ->call('register')
            ->assertHasFormErrors(['turnstile_token']);

        $this->assertDatabaseMissing(User::class, ['email' => 'petr@example.com']);
    }
}
