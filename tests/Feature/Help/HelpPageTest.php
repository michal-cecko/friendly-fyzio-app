<?php

namespace Tests\Feature\Help;

use App\Filament\Pages\Help;
use App\Filament\Support\Help\HelpRepository;
use App\Filament\Support\Help\HelpVersions;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        // Drive the page from the fixture tree so the assertions do not depend on
        // the wording of the shipped articles — the live manual and the archive alike.
        $this->app->bind(HelpRepository::class, fn (): HelpRepository => new HelpRepository(
            base_path('tests/Fixtures/help'),
        ));

        $this->app->bind(HelpVersions::class, fn (): HelpVersions => new HelpVersions(
            base_path('tests/Fixtures/help-versions'),
        ));
    }

    public function test_it_opens_on_the_first_topic(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Help::class)
            ->assertOk()
            ->assertSee('Alfa článek')          // in the tree
            ->assertSee('Rozbity frontmatter'); // the default article's heading
    }

    public function test_a_topic_can_be_deep_linked(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::withQueryParams(['tema' => 'druha/alfa'])
            ->test(Help::class)
            ->assertOk()
            ->assertSee('Tělo článku');
    }

    public function test_an_unknown_topic_falls_back_instead_of_erroring(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::withQueryParams(['tema' => 'neexistuje/vubec'])
            ->test(Help::class)
            ->assertOk()
            ->assertSee('Rozbity frontmatter');
    }

    public function test_opening_a_topic_clears_the_search(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Help::class)
            ->set('q', 'alfa')
            ->call('openTopic', 'druha/alfa')
            ->assertSet('topic', 'druha/alfa')
            ->assertSet('q', '');
    }

    public function test_searching_narrows_the_sidebar(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // The matched word is wrapped in <mark>, so the title is not contiguous in
        // the markup — assert on the section label and on the highlight itself.
        Livewire::test(Help::class)
            ->set('q', 'alfa')
            ->assertSee('Druhá sekce')
            ->assertSee('<mark', escape: false)
            ->assertDontSee('Bez frontmatteru');
    }

    public function test_a_search_with_no_hits_says_so(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Help::class)
            ->set('q', 'nenajdenicnikde')
            ->assertSee('Nic jsme nenašli');
    }

    public function test_a_therapist_can_read_the_help(): void
    {
        // The manual is deliberately ungated — the people with the narrowest panel
        // are the ones most likely to need it.
        $this->actingAs(User::factory()->therapist()->create());

        Livewire::test(Help::class)->assertOk();
    }

    public function test_the_page_is_reachable_over_http(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(Help::getUrl())->assertOk();
    }

    public function test_an_archived_version_is_read_instead_of_the_live_manual(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Help::class, ['version' => '2020-01-01'])
            ->assertOk()
            ->assertSee('Stará sekce')
            ->assertSee('Tenhle text existuje jenom v archivní verzi')
            // The live tree is not mixed in.
            ->assertDontSee('Alfa článek')
            // ...and the page says out loud which manual is on screen.
            ->assertSee('Prohlížíte archivní verzi')
            ->assertSee('Nápověda — archiv 1. 1. 2020');
    }

    public function test_the_version_lands_in_the_url_path(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertStringEndsWith('/napoveda/2020-01-01', Help::getUrl(['version' => '2020-01-01']));

        $this->get(Help::getUrl(['version' => '2020-01-01']))
            ->assertOk()
            ->assertSee('Zastaralý článek');
    }

    public function test_latest_and_a_bare_url_both_open_the_live_manual(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach ([null, 'latest'] as $version) {
            Livewire::test(Help::class, $version === null ? [] : ['version' => $version])
                ->assertOk()
                ->assertSee('Alfa článek')
                ->assertDontSee('Prohlížíte archivní verzi');
        }
    }

    /**
     * A version that was renamed or never existed must not take the manual down
     * with it — the page falls back to the live tree, as it does for topics.
     */
    public function test_an_unknown_version_falls_back_to_the_live_manual(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Help::class, ['version' => '1999-12-31'])
            ->assertOk()
            ->assertSee('Alfa článek')
            ->assertDontSee('Prohlížíte archivní verzi');
    }

    public function test_searching_stays_inside_the_open_version(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Help::class, ['version' => '2020-01-01'])
            ->set('q', 'archiv')
            ->assertSee('Stará sekce')
            ->assertDontSee('Nic jsme nenašli');

        // The same query finds nothing in the live manual, proving the corpus
        // followed the version rather than staying on resources/help.
        Livewire::test(Help::class)
            ->set('q', 'archiv')
            ->assertSee('Nic jsme nenašli');
    }
}
