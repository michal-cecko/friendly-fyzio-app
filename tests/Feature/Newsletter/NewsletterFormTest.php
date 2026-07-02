<?php

namespace Tests\Feature\Newsletter;

use App\Enums\SettingValueType;
use App\Livewire\NewsletterForm;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class NewsletterFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.mailerlite.key', 'test-key');

        Setting::updateOrCreate(
            ['key' => 'newsletter.mailerlite_group_id'],
            ['value' => '999', 'type' => SettingValueType::Text, 'label' => 'MailerLite skupina (ID)', 'group' => 'Newsletter'],
        );
    }

    public function test_subscribing_sets_the_status_without_reloading(): void
    {
        Http::fake(['connect.mailerlite.com/*' => Http::response(['data' => ['id' => '1']], 201)]);

        Livewire::test(NewsletterForm::class)
            ->set('email', 'new@example.com')
            ->call('subscribe')
            ->assertHasNoErrors()
            ->assertSet('status', 'subscribed')
            ->assertSet('email', '');

        Http::assertSent(fn ($request) => $request->url() === 'https://connect.mailerlite.com/api/subscribers'
            && $request['email'] === 'new@example.com'
            && $request['groups'] === ['999']);
    }

    public function test_an_already_subscribed_email_reports_the_already_status(): void
    {
        Http::fake(['connect.mailerlite.com/*' => Http::response(['data' => ['id' => '1']], 200)]);

        Livewire::test(NewsletterForm::class)
            ->set('email', 'existing@example.com')
            ->call('subscribe')
            ->assertSet('status', 'already');
    }

    public function test_an_api_failure_reports_the_error_status(): void
    {
        Http::fake(['connect.mailerlite.com/*' => Http::response(['message' => 'boom'], 500)]);

        Livewire::test(NewsletterForm::class)
            ->set('email', 'boom@example.com')
            ->call('subscribe')
            ->assertSet('status', 'error');
    }

    public function test_an_invalid_email_is_rejected_without_calling_the_api(): void
    {
        Http::fake();

        Livewire::test(NewsletterForm::class)
            ->set('email', 'not-an-email')
            ->call('subscribe')
            ->assertHasErrors(['email' => 'email'])
            ->assertSet('status', null);

        Http::assertNothingSent();
    }
}
