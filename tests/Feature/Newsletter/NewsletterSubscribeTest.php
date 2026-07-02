<?php

namespace Tests\Feature\Newsletter;

use App\Enums\SettingValueType;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsletterSubscribeTest extends TestCase
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

    public function test_subscribes_a_new_email_and_sends_the_group(): void
    {
        Http::fake(['connect.mailerlite.com/*' => Http::response(['data' => ['id' => '1']], 201)]);

        $this->post(route('newsletter.subscribe'), ['email' => 'new@example.com'])
            ->assertRedirect()
            ->assertSessionHas('newsletter_status', 'subscribed');

        Http::assertSent(fn ($request) => $request->url() === 'https://connect.mailerlite.com/api/subscribers'
            && $request['email'] === 'new@example.com'
            && $request['groups'] === ['999']);
    }

    public function test_reports_an_already_subscribed_email(): void
    {
        Http::fake(['connect.mailerlite.com/*' => Http::response(['data' => ['id' => '1']], 200)]);

        $this->post(route('newsletter.subscribe'), ['email' => 'existing@example.com'])
            ->assertRedirect()
            ->assertSessionHas('newsletter_status', 'already');
    }

    public function test_reports_an_error_when_the_api_fails(): void
    {
        Http::fake(['connect.mailerlite.com/*' => Http::response(['message' => 'boom'], 500)]);

        $this->post(route('newsletter.subscribe'), ['email' => 'boom@example.com'])
            ->assertRedirect()
            ->assertSessionHas('newsletter_status', 'error');
    }

    public function test_rejects_an_invalid_email_without_calling_the_api(): void
    {
        Http::fake();

        $this->post(route('newsletter.subscribe'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        Http::assertNothingSent();
    }
}
