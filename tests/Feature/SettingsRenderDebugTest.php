<?php

namespace Tests\Feature;

use App\Filament\Clusters\System\Pages\NewsletterSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\SettingsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsRenderDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_render(): void
    {
        Filament::setCurrentPanel('admin');
        $this->seed(SettingsSeeder::class);
        Setting::where('key', 'newsletter.mailerlite_group_id')->update(['value' => '165960181248689315']);
        Cache::forget(Settings::CACHE_KEY);

        $this->actingAs(User::factory()->admin()->create());

        $html = Livewire::test(NewsletterSettings::class)->html();

        // Print any snippet mentioning the big number or the field id.
        foreach (explode("\n", $html) as $line) {
            if (str_contains($line, '165960181248689315') || str_contains($line, 'mailerlite_group_id')) {
                fwrite(STDERR, 'LINE: '.mb_substr($line, 0, 600).PHP_EOL);
            }
        }

        // Does the raw big number appear anywhere at all?
        fwrite(STDERR, 'CONTAINS_EXACT: '.(str_contains($html, '165960181248689315') ? 'yes' : 'no').PHP_EOL);
        fwrite(STDERR, 'CONTAINS_TRUNC: '.(str_contains($html, '165960181248689312') || str_contains($html, '1.6596018124869E') ? 'yes' : 'no').PHP_EOL);

        $this->assertTrue(true);
    }
}
