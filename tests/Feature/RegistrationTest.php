<?php

namespace Tests\Feature;

use App\Livewire\PublicRegister;
use App\Models\ClientProfile;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

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

        Livewire::test(PublicRegister::class)
            ->set('first_name', 'Jan')
            ->set('last_name', 'Novák')
            ->set('email', 'jan@example.com')
            ->set('phone', '+420 777 888 999')
            ->set('password', 'password1234')
            ->set('password_confirmation', 'password1234')
            ->set('turnstileToken', 'dummy-token')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'jan@example.com')->firstOrFail();

        $this->assertSame('Jan Novák', $user->name);
        $this->assertTrue($user->isCustomer());
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas(ClientProfile::class, ['user_id' => $user->id]);
        $this->assertAuthenticatedAs($user);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registration_requires_a_turnstile_token(): void
    {
        Livewire::test(PublicRegister::class)
            ->set('first_name', 'Eva')
            ->set('last_name', 'Malá')
            ->set('email', 'eva@example.com')
            ->set('phone', '+420 777 111 222')
            ->set('password', 'password1234')
            ->set('password_confirmation', 'password1234')
            ->call('register')
            ->assertHasErrors(['turnstileToken']);

        $this->assertDatabaseMissing(User::class, ['email' => 'eva@example.com']);
    }

    public function test_registration_rejects_an_invalid_turnstile_token(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false]),
        ]);

        Livewire::test(PublicRegister::class)
            ->set('first_name', 'Petr')
            ->set('last_name', 'Veliký')
            ->set('email', 'petr@example.com')
            ->set('phone', '+420 777 333 444')
            ->set('password', 'password1234')
            ->set('password_confirmation', 'password1234')
            ->set('turnstileToken', 'bad-token')
            ->call('register')
            ->assertHasErrors(['turnstileToken']);

        $this->assertDatabaseMissing(User::class, ['email' => 'petr@example.com']);
    }
}
