<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Support\Reservations\ReservationEmailContext;
use App\Support\Settings;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationEmailContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
    }

    public function test_context_exposes_settings_driven_hour_tokens(): void
    {
        $reservation = Reservation::factory()->create([
            'client_id' => User::factory()->customer(),
        ]);

        $context = ReservationEmailContext::for($reservation);

        $this->assertSame((string) Settings::reminderHours(), $context['pripominka_hodin']);
        $this->assertSame((string) Settings::autoCancelHours(), $context['auto_zruseni_hodin']);
        $this->assertSame((string) Settings::confirmationHours(), $context['potvrzeni_hodin']);
        $this->assertSame((string) Settings::stornoFeePercent(), $context['storno_procenta']);
        // Free-storno window falls back to the clinic-wide setting when the service has no rule.
        $this->assertSame((string) $reservation->cancelBeforeHours(), $context['storno_hodin']);
        $this->assertSame((string) Settings::cancelBeforeHours(), $context['storno_hodin']);
    }

    public function test_hour_tokens_track_changed_settings(): void
    {
        Setting::query()->where('key', 'reservation.reminder_hours')->firstOrFail()->update(['value' => '9']);

        $reservation = Reservation::factory()->create([
            'client_id' => User::factory()->customer(),
        ]);

        $this->assertSame('9', ReservationEmailContext::for($reservation)['pripominka_hodin']);
    }
}
