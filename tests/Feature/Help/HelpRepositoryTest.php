<?php

namespace Tests\Feature\Help;

use App\Filament\Support\Help\HelpRepository;
use App\Filament\Support\Help\HelpTopic;
use Tests\TestCase;

/**
 * The repository reads tests/Fixtures/help rather than the shipped articles, so
 * the parsing rules stay pinned even as the real documentation is rewritten.
 */
class HelpRepositoryTest extends TestCase
{
    private function repository(?string $root = null): HelpRepository
    {
        return new HelpRepository($root ?? base_path('tests/Fixtures/help'));
    }

    public function test_sections_and_topics_follow_the_numeric_prefixes(): void
    {
        $sections = $this->repository()->sections();

        $this->assertSame(['prvni', 'druha'], $sections->pluck('id')->all());
        $this->assertSame(
            ['druha/alfa', 'druha/bez-frontmatteru'],
            $sections->firstWhere('id', 'druha')->topics->pluck('id')->all(),
        );
    }

    public function test_front_matter_becomes_the_topics_metadata(): void
    {
        $topic = $this->repository()->find('druha/alfa');

        $this->assertInstanceOf(HelpTopic::class, $topic);
        $this->assertSame('Alfa článek', $topic->title);
        $this->assertSame('heroicon-o-star', $topic->icon);
        $this->assertSame(['alfa', 'první', 'zkouška'], $topic->keywords);
        $this->assertSame('Druhá sekce', $topic->sectionTitle);
        $this->assertStringContainsString('<strong>alfě</strong>', $topic->html());
    }

    public function test_the_section_file_describes_the_section_and_is_not_an_article(): void
    {
        $section = $this->repository()->sections()->firstWhere('id', 'druha');

        $this->assertSame('Druhá sekce', $section->title);
        $this->assertSame('heroicon-o-beaker', $section->icon);
        $this->assertNotContains('druha/section', $section->topics->pluck('id')->all());
    }

    public function test_a_file_without_front_matter_still_becomes_a_topic(): void
    {
        $topic = $this->repository()->find('druha/bez-frontmatteru');

        // Title falls back to the humanised file name, and the whole file is the body.
        $this->assertSame('Bez frontmatteru', $topic->title);
        $this->assertNull($topic->icon);
        $this->assertSame([], $topic->keywords);
        $this->assertStringContainsString('žádné front-matter', $topic->plainText);
    }

    public function test_broken_front_matter_does_not_break_the_help(): void
    {
        // A malformed article must degrade to "no metadata", never take the page down.
        $topic = $this->repository()->find('prvni/rozbity-frontmatter');

        $this->assertNotNull($topic);
        $this->assertSame('Rozbity frontmatter', $topic->title);
        $this->assertStringContainsString('rozbitým front-matterem', $topic->plainText);
    }

    public function test_plain_text_strips_markup_for_searching(): void
    {
        $topic = $this->repository()->find('druha/alfa');

        $this->assertStringNotContainsString('<strong>', $topic->plainText);
        $this->assertStringNotContainsString('##', $topic->plainText);
        $this->assertStringContainsString('Tělo článku o alfě', $topic->plainText);
    }

    public function test_a_missing_directory_yields_no_sections(): void
    {
        $repository = $this->repository(base_path('tests/Fixtures/help-neexistuje'));

        $this->assertTrue($repository->sections()->isEmpty());
        $this->assertNull($repository->first());
    }

    public function test_find_returns_null_for_an_unknown_or_empty_id(): void
    {
        $this->assertNull($this->repository()->find('neexistuje/vubec'));
        $this->assertNull($this->repository()->find(''));
        $this->assertNull($this->repository()->find(null));
    }

    public function test_the_shipped_articles_parse(): void
    {
        // Guards the real content: a typo in one file's front-matter would otherwise
        // only show up as a silently mistitled article in production.
        $repository = new HelpRepository;

        $this->assertGreaterThan(0, $repository->sections()->count());

        foreach ($repository->topics() as $topic) {
            $this->assertNotSame('', trim($topic->title), "Topic {$topic->id} has no title.");
            $this->assertNotSame('', trim($topic->plainText), "Topic {$topic->id} has no body.");
        }
    }
}
