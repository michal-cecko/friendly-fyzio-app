<?php

namespace Tests\Feature\Auth;

use App\Livewire\ForgotPassword;
use App\Livewire\ResetPassword;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_reset_flow_sets_password_and_verifies_the_email(): void
    {
        Notification::fake();

        // A wizard-created passwordless customer: random password, unverified.
        $user = User::factory()->customer()->create(['email_verified_at' => null]);

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('send')
            ->assertSet('sent', true);

        $token = null;

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'noveheslo123')
            ->set('password_confirmation', 'noveheslo123')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect('/muj-ucet');

        $user->refresh();

        $this->assertTrue(Hash::check('noveheslo123', $user->password));
        // The reset link proved mailbox ownership — the account counts as verified.
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->customer()->create();

        Livewire::test(ResetPassword::class, ['token' => 'nonsense-token'])
            ->set('email', $user->email)
            ->set('password', 'noveheslo123')
            ->set('password_confirmation', 'noveheslo123')
            ->call('resetPassword')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_forgot_password_stays_neutral_for_unknown_emails(): void
    {
        Notification::fake();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'neexistuje@example.cz')
            ->call('send')
            ->assertSet('sent', true);

        Notification::assertNothingSent();
    }
}
