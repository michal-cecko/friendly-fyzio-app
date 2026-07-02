<?php

namespace App\Support\MailerLite;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;

/**
 * Adds subscribers to MailerLite via the Connect API.
 *
 * Stateless and Octane-safe — nothing is cached on the instance. The API key
 * lives in config/services.php; the target group (audience) defaults to the
 * admin-editable `newsletter.mailerlite_group_id` setting.
 */
class MailerLiteClient
{
    protected const BASE_URL = 'https://connect.mailerlite.com/api';

    /**
     * Upsert a subscriber into the configured group.
     *
     * MailerLite's create-subscriber endpoint is an upsert: it returns HTTP 201
     * when the subscriber is newly created and HTTP 200 when an existing record
     * is updated. Any other response is treated as a failure.
     *
     * @throws MailerLiteException when the API key is missing or the request fails
     */
    public function subscribe(string $email, ?string $name = null, ?string $groupId = null): SubscribeResult
    {
        $key = config('services.mailerlite.key');

        if (blank($key)) {
            throw new MailerLiteException('MailerLite API key is not configured.');
        }

        $groupId ??= Settings::get('newsletter.mailerlite_group_id');

        $payload = ['email' => $email];

        if (filled($groupId)) {
            $payload['groups'] = [$groupId];
        }

        if (filled($name)) {
            $payload['fields'] = ['name' => $name];
        }

        $response = Http::withToken($key)
            ->acceptJson()
            ->asJson()
            ->post(self::BASE_URL.'/subscribers', $payload);

        return match ($response->status()) {
            201 => SubscribeResult::Subscribed,
            200 => SubscribeResult::AlreadySubscribed,
            default => throw new MailerLiteException(
                "MailerLite subscribe failed with status {$response->status()}: {$response->body()}"
            ),
        };
    }
}
