<?php

namespace Tests\Feature;

use App\Enums\WaitlistPromotionMode;
use App\Models\Setting;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mode descriptions shown next to the "Když se uvolní místo" radio spell out
 * the configured hold windows, so an admin does not have to look the settings up.
 */
class WaitlistPromotionModeDescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
    }

    public function test_automatic_add_description_states_the_payment_window(): void
    {
        Setting::query()->where('key', 'enrollments.hold_hours')->firstOrFail()->update(['value' => '72']);

        $this->assertStringContainsString(
            'držíme 72 hodin na zaplacení',
            WaitlistPromotionMode::AutomaticAdd->description(),
        );
    }

    public function test_automatic_invite_description_states_the_invite_window(): void
    {
        Setting::query()->where('key', 'enrollments.waitlist_invite_hours')->firstOrFail()->update(['value' => '36']);

        $this->assertStringContainsString(
            'Po dobu 36 hodin',
            WaitlistPromotionMode::AutomaticInvite->description(),
        );
    }

    public function test_descriptions_map_covers_every_mode(): void
    {
        $descriptions = WaitlistPromotionMode::descriptions();

        foreach (WaitlistPromotionMode::cases() as $mode) {
            $this->assertSame($mode->description(), $descriptions[$mode->value]);
        }
    }
}
