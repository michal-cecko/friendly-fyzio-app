<?php

namespace Tests\Feature;

use App\Enums\SettingValueType;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class SuppressNonAdminMailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Counts what actually reaches the mailer, so the listener is exercised for
     * real — Notification::fake() would short-circuit it and prove nothing.
     */
    protected function sentCount(callable $send): int
    {
        $sent = 0;
        Event::listen(MessageSending::class, function () use (&$sent): void {
            $sent++;
        });

        $send();

        return $sent;
    }

    public function test_customers_and_therapists_receive_nothing(): void
    {
        config(['mail.suppress_non_admin' => true]);

        foreach (['customer', 'therapist'] as $state) {
            $user = User::factory()->{$state}()->create();

            $this->assertSame(
                0,
                $this->sentCount(fn () => $user->notify(new SuppressionProbeNotification)),
                "A {$state} must not receive e-mail while the pre-launch guard is on.",
            );
        }
    }

    public function test_administrators_still_receive_their_mail(): void
    {
        config(['mail.suppress_non_admin' => true]);

        $admin = User::factory()->admin()->create();

        $this->assertSame(1, $this->sentCount(fn () => $admin->notify(new SuppressionProbeNotification)));
    }

    public function test_the_clinic_contact_inbox_still_receives_enquiries(): void
    {
        config(['mail.suppress_non_admin' => true]);

        $inbox = 'recepce@friendlyfyzio.cz';
        Setting::query()->updateOrCreate(
            ['key' => 'web.contact_email'],
            ['value' => $inbox, 'type' => SettingValueType::Text, 'group' => 'web', 'label' => 'Kontaktní e-mail'],
        );
        Cache::forget(Settings::CACHE_KEY);

        $this->assertSame(1, $this->sentCount(
            fn () => NotificationFacade::route('mail', $inbox)->notify(new SuppressionProbeNotification),
        ));
    }

    public function test_anonymous_customer_addresses_are_suppressed(): void
    {
        config(['mail.suppress_non_admin' => true]);

        // Waitlist and public-booking mail is addressed anonymously and carries
        // no role, so an unresolvable address must be treated as a customer.
        $this->assertSame(0, $this->sentCount(
            fn () => NotificationFacade::route('mail', 'someone@example.com')->notify(new SuppressionProbeNotification),
        ));
    }

    public function test_switching_the_guard_off_restores_normal_delivery(): void
    {
        config(['mail.suppress_non_admin' => false]);

        $customer = User::factory()->customer()->create();

        $this->assertSame(1, $this->sentCount(fn () => $customer->notify(new SuppressionProbeNotification)));
    }
}

class SuppressionProbeNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Probe')->line('Probe');
    }
}
