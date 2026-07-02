<?php

namespace App\Jobs;

use App\Support\MailerLite\MailerLiteClient;
use App\Support\MailerLite\MailerLiteException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Subscribes a reservation client to the MailerLite newsletter on the queue, so
 * booking latency and API failures never block the reservation. A failed call is
 * reported and swallowed to avoid retry storms against MailerLite's rate limits.
 */
class SubscribeToNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {}

    public function handle(MailerLiteClient $client): void
    {
        try {
            $client->subscribe($this->email, $this->name);
        } catch (MailerLiteException $exception) {
            report($exception);
        }
    }
}
