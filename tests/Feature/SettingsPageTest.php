<?php

namespace Tests\Feature;

use App\Enums\SettingValueType;
use App\Filament\Clusters\System\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings as SettingsHelper;
use Database\Seeders\SettingsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->seed(SettingsSeeder::class);
    }

    public function test_admin_can_render_settings_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Settings::class)->assertOk();
    }

    public function test_saving_updates_value_and_refreshes_helper(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Settings::class)
            ->fillForm(['reservation.block_minutes' => 20])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame('20', Setting::where('key', 'reservation.block_minutes')->value('value'));
        $this->assertSame(20, SettingsHelper::blockMinutes());
    }

    public function test_saving_preserves_a_large_integer_id_string(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Settings::class)
            ->fillForm(['newsletter.mailerlite_group_id' => '165960181248689315'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('165960181248689315', Setting::where('key', 'newsletter.mailerlite_group_id')->value('value'));
    }

    public function test_value_type_round_trips_per_type(): void
    {
        $this->assertSame(15, SettingValueType::Integer->cast('15'));
        $this->assertSame('15', SettingValueType::Integer->serialize(15));

        $this->assertTrue(SettingValueType::Boolean->cast('1'));
        $this->assertSame('0', SettingValueType::Boolean->serialize(false));

        $this->assertSame(['a' => 1], SettingValueType::Json->cast('{"a":1}'));
        $this->assertSame('{"a":1}', SettingValueType::Json->serialize(['a' => 1]));
    }
}
