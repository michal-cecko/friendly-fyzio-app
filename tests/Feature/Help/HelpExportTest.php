<?php

namespace Tests\Feature\Help;

use App\Filament\Pages\Help;
use App\Filament\Support\Help\HelpExport;
use App\Filament\Support\Help\HelpRepository;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HelpExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->app->bind(HelpRepository::class, fn (): HelpRepository => new HelpRepository(
            base_path('tests/Fixtures/help'),
        ));
    }

    protected function markdown(): string
    {
        return app(HelpExport::class)->markdown();
    }

    public function test_it_bundles_every_section_and_article_with_a_table_of_contents(): void
    {
        $markdown = $this->markdown();

        $this->assertStringContainsString('# Nápověda — '.config('app.name'), $markdown);
        $this->assertStringContainsString('## Obsah', $markdown);
        $this->assertStringContainsString('- **Druhá sekce**', $markdown);
        $this->assertStringContainsString('    - Alfa článek', $markdown);

        $this->assertStringContainsString('## Druhá sekce', $markdown);
        $this->assertStringContainsString('### Alfa článek', $markdown);
        $this->assertStringContainsString('*Klíčová slova: alfa, první, zkouška*', $markdown);
        $this->assertStringContainsString('Tělo článku o **alfě**.', $markdown);

        // Every fixture article is present, including the one without front-matter.
        $this->assertStringContainsString('Tenhle soubor nemá žádné front-matter', $markdown);
        $this->assertStringContainsString('Tělo článku s rozbitým front-matterem.', $markdown);
    }

    public function test_article_headings_are_demoted_below_their_article_title(): void
    {
        // The fixture article's own "## Nadpis" must not compete with the "## Druhá
        // sekce" wrapper, or the exported hierarchy reads as one flat list.
        $markdown = $this->markdown();

        $this->assertStringContainsString("\n### Nadpis", $markdown);
        $this->assertStringNotContainsString("\n## Nadpis", $markdown);
    }

    public function test_headings_inside_code_fences_are_left_alone(): void
    {
        $export = new class(app(HelpRepository::class)) extends HelpExport
        {
            public function demote(string $markdown): string
            {
                return $this->demoteHeadings($markdown);
            }
        };

        $demoted = $export->demote("## Nadpis\n\n```bash\n# komentář\n```\n\n###### Šestka");

        $this->assertStringContainsString('### Nadpis', $demoted);
        $this->assertStringContainsString("\n# komentář", $demoted);
        $this->assertStringContainsString('###### Šestka', $demoted);
    }

    public function test_the_filename_is_dated_markdown(): void
    {
        $this->assertMatchesRegularExpression(
            '/^napoveda-[a-z0-9-]+-\d{4}-\d{2}-\d{2}\.md$/',
            app(HelpExport::class)->filename(),
        );
    }

    public function test_an_admin_can_download_the_whole_manual(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Help::class)
            ->callAction(TestAction::make('download'))
            ->assertFileDownloaded(app(HelpExport::class)->filename());
    }

    public function test_the_download_is_hidden_from_non_admins(): void
    {
        $this->actingAs(User::factory()->therapist()->create());

        Livewire::test(Help::class)
            ->assertOk()
            ->assertActionHidden(TestAction::make('download'));
    }
}
