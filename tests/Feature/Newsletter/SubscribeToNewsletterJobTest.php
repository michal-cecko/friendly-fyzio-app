<?php

namespace Tests\Feature\Newsletter;

use App\Jobs\SubscribeToNewsletterJob;
use App\Support\MailerLite\MailerLiteClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscribeToNewsletterJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_subscribes_the_email(): void
    {
        config()->set('services.mailerlite.key', 'test-key');
        Http::fake(['connect.mailerlite.com/*' => Http::response(['data' => ['id' => '1']], 201)]);

        (new SubscribeToNewsletterJob('client@example.com', 'Jana Nováková'))->handle(app(MailerLiteClient::class));

        Http::assertSent(fn ($request) => $request['email'] === 'client@example.com'
            && $request['fields'] === ['name' => 'Jana Nováková']);
    }

    public function test_it_swallows_a_mailerlite_failure(): void
    {
        config()->set('services.mailerlite.key', 'test-key');
        Http::fake(['connect.mailerlite.com/*' => Http::response(['message' => 'boom'], 500)]);

        // Should not throw — the job reports and swallows MailerLiteException.
        (new SubscribeToNewsletterJob('client@example.com'))->handle(app(MailerLiteClient::class));

        Http::assertSentCount(1);
    }
}
