<?php

namespace Tests\Feature\Emails;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\ReviewRequest;
use App\Models\User;
use App\Notifications\Auth\FilamentResetPasswordNotification;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailChangeNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Notifications\ClientAccountCreatedNotification;
use App\Notifications\ReviewRequestNotification;
use Database\Seeders\EmailTemplateSeeder;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Filament\Auth\Notifications\VerifyEmailChange as FilamentVerifyEmailChange;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AuthEmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_new_account_and_auth_templates_seed(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        foreach ([
            EmailTemplateKey::EmailVerification,
            EmailTemplateKey::PasswordReset,
            EmailTemplateKey::EmailChangeVerification,
            EmailTemplateKey::AccountCreated,
            EmailTemplateKey::ReviewRequest,
        ] as $key) {
            $this->assertDatabaseHas('email_templates', ['key' => $key->value]);
        }
    }

    public function test_container_binds_the_cms_email_change_notification(): void
    {
        $this->assertInstanceOf(VerifyEmailChangeNotification::class, app(FilamentVerifyEmailChange::class));
    }

    public function test_container_binds_the_cms_admin_password_reset_notification(): void
    {
        $this->assertInstanceOf(FilamentResetPasswordNotification::class, app(FilamentResetPassword::class, ['token' => 'the-token']));
    }

    public function test_verification_email_renders_cms_template_with_the_signed_url(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $user = User::factory()->customer()->create(['name' => 'Jana Nováková']);

        $html = (new VerifyEmailNotification)->toMail($user)->viewData['html'] ?? '';

        // The signed link targets the public verification route.
        $this->assertStringContainsString('/overeni-emailu/'.$user->getKey(), $html);
        $this->assertStringContainsString('Jana', $html);
        $this->assertStringNotContainsString('{{ odkaz }}', $html);
    }

    public function test_verification_email_falls_back_to_laravel_default_when_template_missing(): void
    {
        // No seeding: the template row is absent.
        $user = User::factory()->customer()->create();

        // Parent (Laravel) MailMessage uses an action button, not our rendered view.
        $mail = (new VerifyEmailNotification)->toMail($user);

        $this->assertArrayNotHasKey('html', $mail->viewData);
        $this->assertNotEmpty($mail->actionUrl);
    }

    public function test_password_reset_via_model_sends_cms_notification_with_public_url(): void
    {
        Notification::fake();
        $this->seed(EmailTemplateSeeder::class);

        $user = User::factory()->customer()->create();

        $user->sendPasswordResetNotification('the-token');

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user): bool {
                $html = $notification->toMail($user)->viewData['html'] ?? '';

                return $notification->token === 'the-token'
                    && str_contains($html, '/prihlaseni/obnova-hesla/the-token');
            },
        );
    }

    public function test_admin_forgot_password_page_sends_cms_notification_with_panel_url(): void
    {
        Notification::fake();
        $this->seed(EmailTemplateSeeder::class);

        Filament::setCurrentPanel('admin');

        $user = User::factory()->admin()->create(['name' => 'Jana Nováková']);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request');

        Notification::assertSentTo(
            $user,
            FilamentResetPasswordNotification::class,
            function (FilamentResetPasswordNotification $notification) use ($user): bool {
                $html = $notification->toMail($user)->viewData['html'] ?? '';

                // The link targets the admin panel's own reset page, not the public route.
                return str_contains($html, '/admin/password-reset/reset')
                    && str_contains($html, 'Jana');
            },
        );
    }

    public function test_admin_password_reset_email_falls_back_to_laravel_default_when_template_missing(): void
    {
        // No seeding: the template row is absent.
        $user = User::factory()->admin()->create();

        $notification = new FilamentResetPasswordNotification('the-token');
        $notification->url = 'https://example.test/admin/password-reset/reset?token=the-token';

        $mail = $notification->toMail($user);

        $this->assertArrayNotHasKey('html', $mail->viewData);
        $this->assertSame('https://example.test/admin/password-reset/reset?token=the-token', $mail->actionUrl);
    }

    public function test_email_change_verification_recovers_name_and_new_email_from_url(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $user = User::factory()->customer()->create(['name' => 'Jana Nováková']);

        $notification = new VerifyEmailChangeNotification;
        $notification->url = 'https://example.test/verify-change?id='.$user->getKey().'&email='.urlencode(Crypt::encrypt('jana.nova@example.cz')).'&signature=abc';

        $html = $notification->toMail(null)->viewData['html'] ?? '';

        $this->assertStringContainsString('Jana', $html);
        $this->assertStringContainsString('jana.nova@example.cz', $html);
    }

    public function test_account_created_email_renders_login_link(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $user = User::factory()->customer()->create(['name' => 'Jana Nováková']);

        $html = (new ClientAccountCreatedNotification)->toMail($user)->viewData['html'] ?? '';

        $this->assertStringContainsString(url('/prihlaseni'), $html);
        $this->assertStringContainsString('Jana', $html);
    }

    public function test_review_request_email_renders_target_intro_and_link(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $client = User::factory()->customer()->create(['name' => 'Jana Nováková']);
        $reviewRequest = ReviewRequest::factory()->create();

        $mail = (new ReviewRequestNotification($reviewRequest, 'Vlastní úvodní text.'))->toMail($client);
        $html = $mail->viewData['html'] ?? '';

        // With no reviews.email_subject setting, the subject falls back to the template's.
        $this->assertSame(EmailTemplate::forKey(EmailTemplateKey::ReviewRequest)->subject, $mail->subject);
        $this->assertStringContainsString('Vlastní úvodní text.', $html);
        $this->assertStringContainsString($reviewRequest->formUrl(), $html);
        $this->assertStringContainsString($reviewRequest->targetLabel(), $html);
    }
}
