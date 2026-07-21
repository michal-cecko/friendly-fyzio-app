<?php

namespace Tests\Feature\Credits;

use App\Enums\CreditTransactionType;
use App\Enums\SettingValueType;
use App\Models\CreditTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CreditsExpiringNotification;
use App\Support\Credits\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyExpiringCreditsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function setNoticeDays(int $days): void
    {
        Setting::updateOrCreate(['key' => 'credits.expiry_notice_days'], [
            'value' => (string) $days,
            'type' => SettingValueType::Integer,
            'label' => 'Upozornění na vypršení kreditu',
            'group' => 'Platby',
        ]);
    }

    public function test_notifies_client_of_top_up_expiring_within_window(): void
    {
        Notification::fake();
        $this->setNoticeDays(7);

        $client = User::factory()->customer()->create();
        $topUp = CreditLedger::record($client, 500, CreditTransactionType::TopUp, expiresAt: now()->addDays(3));

        $this->artisan('credits:notify-expiring')->assertSuccessful();

        Notification::assertSentTo($client, CreditsExpiringNotification::class);
        $this->assertNotNull($topUp->fresh()->expiry_notified_at);
    }

    public function test_notification_is_sent_only_once(): void
    {
        Notification::fake();
        $this->setNoticeDays(7);

        $client = User::factory()->customer()->create();
        CreditLedger::record($client, 500, CreditTransactionType::TopUp, expiresAt: now()->addDays(3));

        $this->artisan('credits:notify-expiring')->assertSuccessful();
        $this->artisan('credits:notify-expiring')->assertSuccessful();

        Notification::assertSentToTimes($client, CreditsExpiringNotification::class, 1);
    }

    public function test_top_up_expiring_outside_window_is_not_notified(): void
    {
        Notification::fake();
        $this->setNoticeDays(7);

        $client = User::factory()->customer()->create();
        CreditLedger::record($client, 500, CreditTransactionType::TopUp, expiresAt: now()->addDays(30));

        $this->artisan('credits:notify-expiring')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_already_expired_top_up_is_not_notified(): void
    {
        Notification::fake();
        $this->setNoticeDays(7);

        $client = User::factory()->customer()->create();
        $topUp = CreditLedger::record($client, 500, CreditTransactionType::TopUp, expiresAt: now()->addDays(3));
        // Simulate the credits:expire sweep having already written this top-up off.
        CreditLedger::record($client, -500, CreditTransactionType::Expiration, 'Propadnutí', related: $topUp);

        $this->artisan('credits:notify-expiring')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_disabled_when_notice_days_is_zero(): void
    {
        Notification::fake();
        $this->setNoticeDays(0);

        $client = User::factory()->customer()->create();
        CreditLedger::record($client, 500, CreditTransactionType::TopUp, expiresAt: now()->addDays(3));

        $this->artisan('credits:notify-expiring')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_client_with_multiple_expiring_top_ups_gets_one_email(): void
    {
        Notification::fake();
        $this->setNoticeDays(7);

        $client = User::factory()->customer()->create();
        CreditLedger::record($client, 500, CreditTransactionType::TopUp, expiresAt: now()->addDays(2));
        CreditLedger::record($client, 300, CreditTransactionType::TopUp, expiresAt: now()->addDays(5));

        $this->artisan('credits:notify-expiring')->assertSuccessful();

        Notification::assertSentToTimes($client, CreditsExpiringNotification::class, 1);
        $this->assertSame(
            0,
            CreditTransaction::query()->where('client_id', $client->getKey())->whereNull('expiry_notified_at')->count(),
        );
    }

    public function test_fully_spent_top_up_is_not_notified(): void
    {
        Notification::fake();
        $this->setNoticeDays(7);

        $client = User::factory()->customer()->create();
        CreditLedger::record($client, 500, CreditTransactionType::TopUp, expiresAt: now()->addDays(3));
        CreditLedger::record($client, -500, CreditTransactionType::Deduction, 'Terapie');

        $this->artisan('credits:notify-expiring')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
