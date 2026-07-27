<?php

namespace Tests\Feature;

use App\Filament\Clusters\System\Pages\RezervaceSettings;
use App\Filament\Support\Help\HelpRepository;
use App\Filament\Support\Search\PanelGlobalSearchProvider;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PanelGlobalSearchProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * @return Collection<int, GlobalSearchResult>
     */
    protected function category(string $query, string $label): Collection
    {
        $categories = app(PanelGlobalSearchProvider::class)->getResults($query)?->getCategories() ?? collect();

        $this->assertTrue(
            $categories->has($label),
            "Expected a '{$label}' category, got: ".$categories->keys()->implode(', '),
        );

        return collect($categories[$label]);
    }

    protected function useHelpFixtures(): void
    {
        $this->app->bind(HelpRepository::class, fn (): HelpRepository => new HelpRepository(
            base_path('tests/Fixtures/help'),
        ));
    }

    public function test_panel_uses_the_extended_global_search_provider(): void
    {
        $this->assertInstanceOf(
            PanelGlobalSearchProvider::class,
            Filament::getCurrentOrDefaultPanel()->getGlobalSearchProvider(),
        );
    }

    public function test_records_are_still_returned(): void
    {
        User::factory()->customer()->create(['name' => 'Zdislava Vyhledávaná']);

        $titles = collect(app(PanelGlobalSearchProvider::class)->getResults('Zdislava Vyhledávaná')?->getCategories())
            ->flatMap(fn (iterable $results): Collection => collect($results)->map(
                fn (GlobalSearchResult $result): string => $result->title,
            ));

        $this->assertContains('Zdislava Vyhledávaná', $titles);
    }

    public function test_settings_are_returned_and_deep_link_to_their_field(): void
    {
        $setting = Setting::factory()->create([
            'key' => 'reservation.block_duration',
            'group' => 'Rezervace',
            'label' => 'Délka rezervačního bloku',
        ]);

        $result = $this->category('rezervačního bloku', 'Nastavení')->first();

        $this->assertSame('Délka rezervačního bloku', $result->title);
        $this->assertSame('Rezervace', $result->details['Sekce']);
        $this->assertStringContainsString(RezervaceSettings::getUrl(), $result->url);
        $this->assertStringEndsWith('#'.$setting->anchor(), $result->url);
    }

    public function test_help_topics_are_returned(): void
    {
        $this->useHelpFixtures();

        $result = $this->category('alfa', 'Nápověda')->first();

        $this->assertSame('Alfa článek', $result->title);
        $this->assertStringContainsString('tema=druha%2Falfa', $result->url);
    }

    public function test_settings_stay_hidden_from_non_admins_while_help_does_not(): void
    {
        $this->useHelpFixtures();

        Setting::factory()->create([
            'group' => 'Rezervace',
            'label' => 'Tajné alfa nastavení',
        ]);

        $this->actingAs(User::factory()->therapist()->create());

        $categories = app(PanelGlobalSearchProvider::class)->getResults('alfa')?->getCategories();

        $this->assertFalse($categories->has('Nastavení'));
        $this->assertTrue($categories->has('Nápověda'));
    }

    public function test_each_appended_category_is_capped(): void
    {
        Setting::factory()->count(PanelGlobalSearchProvider::RESULTS_PER_CATEGORY + 3)
            ->sequence(fn ($sequence) => [
                'key' => "reservation.hromadne_{$sequence->index}",
                'label' => "Hromadné nastavení {$sequence->index}",
            ])
            ->create(['group' => 'Rezervace']);

        $this->assertCount(
            PanelGlobalSearchProvider::RESULTS_PER_CATEGORY,
            $this->category('Hromadné nastavení', 'Nastavení'),
        );
    }

    public function test_short_queries_return_no_appended_categories(): void
    {
        $this->useHelpFixtures();

        Setting::factory()->create(['group' => 'Rezervace', 'label' => 'A nastavení']);

        $categories = app(PanelGlobalSearchProvider::class)->getResults('a')?->getCategories();

        $this->assertFalse($categories->has('Nastavení'));
        $this->assertFalse($categories->has('Nápověda'));
    }
}
