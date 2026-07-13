<?php

namespace Tests\Feature;

use App\Filament\Pages\Search;
use App\Filament\Support\Search\RecentSearches;
use App\Models\Reservation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_page_renders(): void
    {
        $this->get(Search::getUrl())->assertSuccessful();
    }

    public function test_page_is_hidden_from_navigation(): void
    {
        $this->assertFalse(Search::shouldRegisterNavigation());
    }

    public function test_query_deep_link_fills_state_and_renders_results(): void
    {
        $client = User::factory()->customer()->create(['name' => 'Zdislava Deeplinková']);

        Reservation::factory()->create(['client_id' => $client->getKey()]);

        Livewire::withQueryParams(['q' => 'Zdislava Deeplinková'])
            ->test(Search::class)
            ->assertSet('q', 'Zdislava Deeplinková')
            // The matched substrings render wrapped in <mark> highlights.
            ->assertSeeHtml('Deeplinková</mark>');
    }

    public function test_trashed_records_show_deleted_badge_and_can_be_toggled_off(): void
    {
        $client = User::factory()->customer()->create(['name' => 'Zdislava Odstraněná']);

        Reservation::factory()->create(['client_id' => $client->getKey()])->delete();

        Livewire::test(Search::class)
            ->set('q', 'Zdislava Odstraněná')
            ->assertSee('Smazáno')
            ->set('includeTrashed', false)
            ->assertDontSee('Smazáno');
    }

    public function test_searches_are_recorded_and_shown_as_recent_when_query_is_empty(): void
    {
        Livewire::test(Search::class)
            ->set('q', 'pilates pro těhotné')
            ->set('q', '')
            ->assertSee('Nedávná hledání')
            ->assertSee('pilates pro těhotné');

        $this->assertSame(['pilates pro těhotné'], app(RecentSearches::class)->all());
    }

    public function test_recording_replaces_earlier_prefixes_of_the_same_term(): void
    {
        $recentSearches = app(RecentSearches::class);

        $recentSearches->record('pil');
        $recentSearches->record('pilates');
        $recentSearches->record('pilates pro');

        $this->assertSame(['pilates pro'], $recentSearches->all());
    }

    public function test_recent_searches_can_be_removed_and_cleared(): void
    {
        $recentSearches = app(RecentSearches::class);

        $recentSearches->record('masáž zad');
        $recentSearches->record('fyzioterapie');

        Livewire::test(Search::class)
            ->call('forgetRecentSearch', 'masáž zad')
            ->assertDontSee('masáž zad')
            ->assertSee('fyzioterapie')
            ->call('clearRecentSearches')
            ->assertDontSee('fyzioterapie');

        $this->assertSame([], $recentSearches->all());
    }

    public function test_recent_search_chip_runs_the_search_again(): void
    {
        app(RecentSearches::class)->record('fyzioterapie');

        Livewire::test(Search::class)
            ->call('searchFor', 'fyzioterapie')
            ->assertSet('q', 'fyzioterapie');
    }
}
